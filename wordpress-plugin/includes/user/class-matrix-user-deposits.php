<?php
/**
 * User Deposits
 */

if (!defined('ABSPATH')) {
    exit;
}

class Matrix_MLM_User_Deposits {

    public function render_deposit_form($user_id) {
        global $wpdb;
        $gateways = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}matrix_gateways WHERE status = 1");
        $currency = get_option('matrix_mlm_currency_symbol', '₦');
        $min = get_option('matrix_mlm_min_deposit', 1000);
        $max = get_option('matrix_mlm_max_deposit', 5000000);
        ?>
        <h2><?php _e('Make a Deposit', 'matrix-mlm'); ?></h2>
        <div class="matrix-form-card">
            <form id="matrix-deposit-form" class="matrix-form">
                <div class="matrix-form-group">
                    <label><?php _e('Amount', 'matrix-mlm'); ?> (<?php echo $currency; ?>)</label>
                    <input type="number" name="amount" min="<?php echo $min; ?>" max="<?php echo $max; ?>" step="0.01" required placeholder="<?php echo sprintf(__('Min: %s, Max: %s', 'matrix-mlm'), number_format($min), number_format($max)); ?>">
                </div>
                <div class="matrix-form-group">
                    <label><?php _e('Payment Gateway', 'matrix-mlm'); ?></label>
                    <div class="matrix-gateway-options">
                        <?php foreach ($gateways as $gw): ?>
                        <label class="matrix-gateway-option">
                            <input type="radio" name="gateway" value="<?php echo esc_attr($gw->slug); ?>" required>
                            <span class="gateway-name"><?php echo esc_html($gw->name); ?></span>
                            <?php if ($gw->fixed_charge > 0 || $gw->percent_charge > 0): ?>
                            <small class="gateway-charge"><?php echo sprintf(__('Charge: %s + %s%%', 'matrix-mlm'), $currency . number_format($gw->fixed_charge, 2), $gw->percent_charge); ?></small>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="matrix-btn matrix-btn-primary matrix-btn-block"><?php _e('Proceed to Payment', 'matrix-mlm'); ?></button>
            </form>
        </div>
        <?php
    }

    public function render_history($user_id) {
        global $wpdb;
        $currency = get_option('matrix_mlm_currency_symbol', '₦');
        $deposits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}matrix_deposits WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ));
        ?>
        <h2><?php _e('Deposit History', 'matrix-mlm'); ?></h2>
        <table class="matrix-table">
            <thead><tr><th><?php _e('Date', 'matrix-mlm'); ?></th><th><?php _e('Gateway', 'matrix-mlm'); ?></th><th><?php _e('Amount', 'matrix-mlm'); ?></th><th><?php _e('Charge', 'matrix-mlm'); ?></th><th><?php _e('Net', 'matrix-mlm'); ?></th><th><?php _e('Status', 'matrix-mlm'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($deposits as $d): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i', strtotime($d->created_at)); ?></td>
                    <td><?php echo esc_html(ucfirst($d->gateway)); ?></td>
                    <td><?php echo $currency . number_format($d->amount, 2); ?></td>
                    <td><?php echo $currency . number_format($d->charge, 2); ?></td>
                    <td><?php echo $currency . number_format($d->net_amount, 2); ?></td>
                    <td><span class="matrix-badge matrix-badge-<?php echo $d->status; ?>"><?php echo ucfirst($d->status); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
