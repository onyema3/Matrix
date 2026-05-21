<?php
/**
 * Fintava Payment Gateway - Bank Transfer/Payout
 * 
 * Integrates with the Fintava API (https://fintava.readme.io/reference/getting-started-with-fintava-api)
 * to allow users to transfer funds from their Matrix wallet to their bank accounts.
 * 
 * Endpoints used:
 * - GET  /banks           - List supported banks
 * - POST /resolve-account - Verify bank account (name lookup)
 * - POST /transfer        - Initiate bank transfer/payout
 * - GET  /transfer/:id    - Check transfer status
 */

if (!defined('ABSPATH')) {
    exit;
}

class Matrix_MLM_Fintava {

    private $secret_key;
    private $public_key;
    private $base_url;
    private $environment;

    public function __construct() {
        $this->load_credentials();
        $this->register_hooks();
    }

    /**
     * Load API credentials from settings
     */
    private function load_credentials() {
        $this->environment = get_option('matrix_mlm_fintava_environment', 'sandbox');
        $this->secret_key = get_option('matrix_mlm_fintava_secret_key', '');
        $this->public_key = get_option('matrix_mlm_fintava_public_key', '');

        // Set base URL based on environment
        if ($this->environment === 'live') {
            $this->base_url = 'https://api.fintava.com/v1';
        } else {
            $this->base_url = 'https://sandbox.fintava.com/v1';
        }

        // Allow override via settings
        $custom_url = get_option('matrix_mlm_fintava_base_url', '');
        if (!empty($custom_url)) {
            $this->base_url = rtrim($custom_url, '/');
        }
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action('wp_ajax_matrix_fintava_get_banks', [$this, 'ajax_get_banks']);
        add_action('wp_ajax_matrix_fintava_resolve_account', [$this, 'ajax_resolve_account']);
        add_action('wp_ajax_matrix_fintava_initiate_transfer', [$this, 'ajax_initiate_transfer']);
        add_action('wp_ajax_matrix_fintava_check_status', [$this, 'ajax_check_transfer_status']);

        // REST API endpoint for webhook callbacks
        add_action('rest_api_init', [$this, 'register_webhook_routes']);
    }

    /**
     * Register webhook route for transfer status updates
     */
    public function register_webhook_routes() {
        register_rest_route('matrix-mlm/v1', '/fintava/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // API METHODS
    // =========================================================================

    /**
     * Get list of supported banks
     * GET /banks
     */
    public function get_banks() {
        $cache_key = 'matrix_fintava_banks_list';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = $this->make_request('GET', '/banks');

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
            // Cache bank list for 24 hours
            set_transient($cache_key, $response['data'], DAY_IN_SECONDS);
            return $response['data'];
        }

        return new WP_Error('fintava_error', $response['message'] ?? __('Failed to retrieve bank list', 'matrix-mlm'));
    }

    /**
     * Resolve/verify bank account details (name lookup)
     * POST /resolve-account
     * 
     * @param string $account_number The bank account number
     * @param string $bank_code The bank code
     * @return array|WP_Error Account details or error
     */
    public function resolve_account($account_number, $bank_code) {
        $response = $this->make_request('POST', '/resolve-account', [
            'account_number' => $account_number,
            'bank_code' => $bank_code,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
            return $response['data'];
        }

        return new WP_Error(
            'fintava_resolve_error',
            $response['message'] ?? __('Could not resolve account details', 'matrix-mlm')
        );
    }

    /**
     * Initiate a bank transfer/payout
     * POST /transfer
     * 
     * @param array $transfer_data Transfer parameters
     * @return array|WP_Error Transfer result or error
     */
    public function initiate_transfer($transfer_data) {
        $required_fields = ['amount', 'account_number', 'bank_code', 'narration'];
        foreach ($required_fields as $field) {
            if (empty($transfer_data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'matrix-mlm'), $field));
            }
        }

        $payload = [
            'amount' => floatval($transfer_data['amount']),
            'account_number' => sanitize_text_field($transfer_data['account_number']),
            'bank_code' => sanitize_text_field($transfer_data['bank_code']),
            'narration' => sanitize_text_field($transfer_data['narration']),
            'currency' => $transfer_data['currency'] ?? 'NGN',
            'reference' => $transfer_data['reference'] ?? $this->generate_reference(),
            'callback_url' => rest_url('matrix-mlm/v1/fintava/webhook'),
        ];

        // Optional fields
        if (!empty($transfer_data['account_name'])) {
            $payload['account_name'] = sanitize_text_field($transfer_data['account_name']);
        }
        if (!empty($transfer_data['bank_name'])) {
            $payload['bank_name'] = sanitize_text_field($transfer_data['bank_name']);
        }

        $response = $this->make_request('POST', '/transfer', $payload);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['status']) && $response['status'] === true) {
            return [
                'success' => true,
                'transfer_id' => $response['data']['id'] ?? null,
                'reference' => $response['data']['reference'] ?? $payload['reference'],
                'status' => $response['data']['status'] ?? 'pending',
                'message' => $response['message'] ?? __('Transfer initiated successfully', 'matrix-mlm'),
            ];
        }

        return new WP_Error(
            'fintava_transfer_error',
            $response['message'] ?? __('Transfer failed', 'matrix-mlm')
        );
    }

    /**
     * Check transfer status
     * GET /transfer/:id
     * 
     * @param string $transfer_id The transfer ID or reference
     * @return array|WP_Error
     */
    public function check_transfer_status($transfer_id) {
        $response = $this->make_request('GET', '/transfer/' . $transfer_id);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
            return $response['data'];
        }

        return new WP_Error(
            'fintava_status_error',
            $response['message'] ?? __('Could not fetch transfer status', 'matrix-mlm')
        );
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * AJAX: Get bank list
     */
    public function ajax_get_banks() {
        check_ajax_referer('matrix_mlm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Authentication required', 'matrix-mlm')]);
        }

        $banks = $this->get_banks();

        if (is_wp_error($banks)) {
            wp_send_json_error(['message' => $banks->get_error_message()]);
        }

        wp_send_json_success(['banks' => $banks]);
    }

    /**
     * AJAX: Resolve bank account
     */
    public function ajax_resolve_account() {
        check_ajax_referer('matrix_mlm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Authentication required', 'matrix-mlm')]);
        }

        $account_number = sanitize_text_field($_POST['account_number'] ?? '');
        $bank_code = sanitize_text_field($_POST['bank_code'] ?? '');

        if (empty($account_number) || empty($bank_code)) {
            wp_send_json_error(['message' => __('Account number and bank are required', 'matrix-mlm')]);
        }

        if (!preg_match('/^\d{10}$/', $account_number)) {
            wp_send_json_error(['message' => __('Account number must be 10 digits', 'matrix-mlm')]);
        }

        $result = $this->resolve_account($account_number, $bank_code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'account_name' => $result['account_name'] ?? '',
            'account_number' => $result['account_number'] ?? $account_number,
            'bank_name' => $result['bank_name'] ?? '',
        ]);
    }

    /**
     * AJAX: Initiate bank transfer from wallet
     */
    public function ajax_initiate_transfer() {
        check_ajax_referer('matrix_mlm_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('Authentication required', 'matrix-mlm')]);
        }

        // Check if user is active
        if (!Matrix_MLM_User::is_active($user_id)) {
            wp_send_json_error(['message' => __('Your account is suspended', 'matrix-mlm')]);
        }

        // Validate inputs
        $amount = floatval($_POST['amount'] ?? 0);
        $account_number = sanitize_text_field($_POST['account_number'] ?? '');
        $bank_code = sanitize_text_field($_POST['bank_code'] ?? '');
        $bank_name = sanitize_text_field($_POST['bank_name'] ?? '');
        $account_name = sanitize_text_field($_POST['account_name'] ?? '');
        $narration = sanitize_text_field($_POST['narration'] ?? '');

        if (empty($narration)) {
            $narration = sprintf('Matrix Payout - %s', wp_get_current_user()->user_login);
        }

        // Validate amount limits
        $min_payout = floatval(get_option('matrix_mlm_fintava_min_payout', 1000));
        $max_payout = floatval(get_option('matrix_mlm_fintava_max_payout', 5000000));

        if ($amount < $min_payout) {
            wp_send_json_error(['message' => sprintf(__('Minimum payout amount is %s%s', 'matrix-mlm'), get_option('matrix_mlm_currency_symbol', '₦'), number_format($min_payout, 2))]);
        }

        if ($amount > $max_payout) {
            wp_send_json_error(['message' => sprintf(__('Maximum payout amount is %s%s', 'matrix-mlm'), get_option('matrix_mlm_currency_symbol', '₦'), number_format($max_payout, 2))]);
        }

        if (empty($account_number) || empty($bank_code)) {
            wp_send_json_error(['message' => __('Bank account details are required', 'matrix-mlm')]);
        }

        // Calculate charges
        $charge_type = get_option('matrix_mlm_fintava_charge_type', 'fixed');
        $charge_value = floatval(get_option('matrix_mlm_fintava_charge_value', 50));

        if ($charge_type === 'percent') {
            $charge = round($amount * $charge_value / 100, 2);
        } else {
            $charge = $charge_value;
        }

        $total_debit = $amount + $charge;

        // Check wallet balance
        $wallet = new Matrix_MLM_Wallet();
        $balance = $wallet->get_balance($user_id);

        if ($balance < $total_debit) {
            wp_send_json_error(['message' => sprintf(
                __('Insufficient balance. You need %s%s (Amount: %s%s + Charge: %s%s)', 'matrix-mlm'),
                get_option('matrix_mlm_currency_symbol', '₦'), number_format($total_debit, 2),
                get_option('matrix_mlm_currency_symbol', '₦'), number_format($amount, 2),
                get_option('matrix_mlm_currency_symbol', '₦'), number_format($charge, 2)
            )]);
        }

        // Generate reference
        $reference = $this->generate_reference();

        // Debit wallet first (hold funds)
        $wallet->debit(
            $user_id,
            $total_debit,
            'fintava_payout',
            sprintf(__('Bank transfer to %s (%s) - Ref: %s', 'matrix-mlm'), $account_name, $bank_name, $reference),
            $reference
        );

        // Record the payout in database
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'matrix_fintava_payouts', [
            'user_id' => $user_id,
            'reference' => $reference,
            'amount' => $amount,
            'charge' => $charge,
            'total_debit' => $total_debit,
            'bank_code' => $bank_code,
            'bank_name' => $bank_name,
            'account_number' => $account_number,
            'account_name' => $account_name,
            'narration' => $narration,
            'currency' => 'NGN',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $payout_id = $wpdb->insert_id;

        // Initiate transfer via Fintava API
        $result = $this->initiate_transfer([
            'amount' => $amount,
            'account_number' => $account_number,
            'bank_code' => $bank_code,
            'bank_name' => $bank_name,
            'account_name' => $account_name,
            'narration' => $narration,
            'reference' => $reference,
            'currency' => 'NGN',
        ]);

        if (is_wp_error($result)) {
            // Transfer failed - refund the user
            $wallet->credit(
                $user_id,
                $total_debit,
                'fintava_payout_refund',
                sprintf(__('Refund: Bank transfer failed - %s', 'matrix-mlm'), $result->get_error_message()),
                $reference
            );

            // Update payout status
            $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
                'status' => 'failed',
                'failure_reason' => $result->get_error_message(),
                'updated_at' => current_time('mysql'),
            ], ['id' => $payout_id]);

            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        // Update payout with transfer details
        $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
            'transfer_id' => $result['transfer_id'] ?? '',
            'status' => $result['status'] ?? 'processing',
            'updated_at' => current_time('mysql'),
        ], ['id' => $payout_id]);

        // Send notification
        $currency = get_option('matrix_mlm_currency_symbol', '₦');
        Matrix_MLM_Notifications::send_admin_notification(
            'fintava_payout',
            sprintf(
                __('Bank payout initiated: %s%s to %s (%s - %s). Ref: %s', 'matrix-mlm'),
                $currency, number_format($amount, 2), $account_name, $bank_name, $account_number, $reference
            )
        );

        wp_send_json_success([
            'message' => sprintf(
                __('Transfer of %s%s initiated to %s (%s). It will be processed shortly.', 'matrix-mlm'),
                $currency, number_format($amount, 2), $account_name, $bank_name
            ),
            'reference' => $reference,
            'status' => $result['status'] ?? 'processing',
        ]);
    }

    /**
     * AJAX: Check transfer status
     */
    public function ajax_check_transfer_status() {
        check_ajax_referer('matrix_mlm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Authentication required', 'matrix-mlm')]);
        }

        $reference = sanitize_text_field($_POST['reference'] ?? '');
        if (empty($reference)) {
            wp_send_json_error(['message' => __('Reference is required', 'matrix-mlm')]);
        }

        global $wpdb;
        $payout = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}matrix_fintava_payouts WHERE reference = %s AND user_id = %d",
            $reference, get_current_user_id()
        ));

        if (!$payout) {
            wp_send_json_error(['message' => __('Payout not found', 'matrix-mlm')]);
        }

        // If we have a transfer_id, check with Fintava
        if (!empty($payout->transfer_id) && in_array($payout->status, ['pending', 'processing'])) {
            $status = $this->check_transfer_status($payout->transfer_id);
            if (!is_wp_error($status)) {
                $new_status = $status['status'] ?? $payout->status;
                if ($new_status !== $payout->status) {
                    $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
                        'status' => $new_status,
                        'updated_at' => current_time('mysql'),
                    ], ['id' => $payout->id]);
                    $payout->status = $new_status;
                }
            }
        }

        wp_send_json_success([
            'reference' => $payout->reference,
            'amount' => $payout->amount,
            'bank_name' => $payout->bank_name,
            'account_name' => $payout->account_name,
            'account_number' => $payout->account_number,
            'status' => $payout->status,
            'created_at' => $payout->created_at,
        ]);
    }

    // =========================================================================
    // WEBHOOK HANDLER
    // =========================================================================

    /**
     * Handle Fintava webhook notifications for transfer status updates
     */
    public function handle_webhook($request) {
        $payload = $request->get_body();
        $signature = $request->get_header('x-fintava-signature');

        // Verify webhook signature
        $webhook_secret = get_option('matrix_mlm_fintava_webhook_secret', '');
        if (!empty($webhook_secret)) {
            $computed_signature = hash_hmac('sha512', $payload, $webhook_secret);
            if (!hash_equals($computed_signature, $signature ?? '')) {
                return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
        }

        $event = json_decode($payload, true);
        if (!$event) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $event_type = $event['event'] ?? '';
        $data = $event['data'] ?? [];

        switch ($event_type) {
            case 'transfer.success':
            case 'transfer.completed':
                $this->handle_transfer_success($data);
                break;
            case 'transfer.failed':
            case 'transfer.reversed':
                $this->handle_transfer_failure($data);
                break;
        }

        return new WP_REST_Response(['status' => 'success'], 200);
    }

    /**
     * Handle successful transfer webhook
     */
    private function handle_transfer_success($data) {
        global $wpdb;
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            return;
        }

        $payout = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}matrix_fintava_payouts WHERE reference = %s",
            $reference
        ));

        if (!$payout || $payout->status === 'completed') {
            return;
        }

        $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $payout->id]);

        // Send success notification to user
        $currency = get_option('matrix_mlm_currency_symbol', '₦');
        $user = get_userdata($payout->user_id);
        if ($user) {
            Matrix_MLM_Notifications::send_deposit_notification(
                $payout->user_id,
                $payout->amount,
                'completed'
            );
        }
    }

    /**
     * Handle failed/reversed transfer webhook
     */
    private function handle_transfer_failure($data) {
        global $wpdb;
        $reference = $data['reference'] ?? '';
        $reason = $data['reason'] ?? $data['message'] ?? __('Transfer failed', 'matrix-mlm');

        if (empty($reference)) {
            return;
        }

        $payout = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}matrix_fintava_payouts WHERE reference = %s",
            $reference
        ));

        if (!$payout || in_array($payout->status, ['failed', 'refunded'])) {
            return;
        }

        // Update status
        $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
            'status' => 'failed',
            'failure_reason' => $reason,
            'updated_at' => current_time('mysql'),
        ], ['id' => $payout->id]);

        // Refund the user
        $wallet = new Matrix_MLM_Wallet();
        $wallet->credit(
            $payout->user_id,
            $payout->total_debit,
            'fintava_payout_refund',
            sprintf(__('Refund: Bank transfer failed - %s (Ref: %s)', 'matrix-mlm'), $reason, $reference),
            $reference
        );

        // Update to refunded
        $wpdb->update($wpdb->prefix . 'matrix_fintava_payouts', [
            'status' => 'refunded',
            'updated_at' => current_time('mysql'),
        ], ['id' => $payout->id]);

        // Notify user
        Matrix_MLM_Notifications::send_admin_notification(
            'fintava_payout_failed',
            sprintf(__('Bank payout FAILED and refunded. Ref: %s, Reason: %s', 'matrix-mlm'), $reference, $reason)
        );
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Make HTTP request to Fintava API
     */
    private function make_request($method, $endpoint, $body = null) {
        $url = $this->base_url . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $error_message = $body['message'] ?? sprintf(__('API Error (HTTP %d)', 'matrix-mlm'), $status_code);
            return new WP_Error('fintava_api_error', $error_message);
        }

        return $body;
    }

    /**
     * Generate unique transfer reference
     */
    private function generate_reference() {
        return 'MTX-FTV-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12)) . '-' . time();
    }

    /**
     * Check if Fintava is configured and active
     */
    public function is_active() {
        return !empty($this->secret_key) && get_option('matrix_mlm_fintava_enabled', 0);
    }

    /**
     * Get payout history for a user
     */
    public function get_user_payouts($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}matrix_fintava_payouts 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }

    /**
     * Get all payouts (admin)
     */
    public function get_all_payouts($status = null, $limit = 50, $offset = 0) {
        global $wpdb;

        $where = "WHERE 1=1";
        $params = [];

        if ($status) {
            $where .= " AND p.status = %s";
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.user_login, u.user_email 
             FROM {$wpdb->prefix}matrix_fintava_payouts p 
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
             $where ORDER BY p.created_at DESC LIMIT %d OFFSET %d",
            $params
        ));
    }

    /**
     * Create the fintava_payouts database table
     */
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'matrix_fintava_payouts';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            reference varchar(100) NOT NULL UNIQUE,
            transfer_id varchar(100) DEFAULT NULL,
            amount decimal(12,2) NOT NULL,
            charge decimal(12,2) NOT NULL DEFAULT 0.00,
            total_debit decimal(12,2) NOT NULL,
            bank_code varchar(20) NOT NULL,
            bank_name varchar(100) NOT NULL,
            account_number varchar(20) NOT NULL,
            account_name varchar(255) NOT NULL,
            narration varchar(255) DEFAULT NULL,
            currency varchar(5) NOT NULL DEFAULT 'NGN',
            status enum('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',
            failure_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY reference (reference),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
