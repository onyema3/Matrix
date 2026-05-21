<?php
/**
 * User Virtual Wallet (via Fintava)
 * Allows users to create a virtual bank account number linked to their Matrix wallet
 */

if (!defined('ABSPATH')) {
    exit;
}

class Matrix_MLM_User_Virtual_Wallet {

    public function render($user_id) {
        $fintava = new Matrix_MLM_Fintava();
        $is_active = $fintava->is_active();
        $wallet = $fintava->get_user_wallet($user_id);
        $user = get_userdata($user_id);
        $meta = Matrix_MLM_User::get_meta($user_id);
        ?>
        <h2><?php _e('Virtual Wallet', 'matrix-mlm'); ?></h2>
        <p class="matrix-subtitle"><?php _e('Your dedicated virtual bank account number. Receive payments directly into your Matrix wallet.', 'matrix-mlm'); ?></p>

        <?php if (!$is_active): ?>
        <div class="matrix-alert matrix-alert-warning">
            <?php _e('Virtual wallet service is currently unavailable. Please contact support.', 'matrix-mlm'); ?>
        </div>
        <?php elseif ($wallet): ?>
            <!-- Display existing wallet -->
            <?php $this->render_wallet_details($wallet); ?>
        <?php else: ?>
            <!-- Create wallet form -->
            <?php $this->render_create_form($user, $meta); ?>
        <?php endif; ?>

        <style>
        .matrix-subtitle { color: #6b7280; margin: -10px 0 20px; font-size: 14px; }
        .matrix-virtual-wallet-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            padding: 32px;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
        }
        .matrix-virtual-wallet-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 300px;
            height: 300px;
            background: rgba(79, 70, 229, 0.15);
            border-radius: 50%;
        }
        .matrix-virtual-wallet-card::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 250px;
            height: 250px;
            background: rgba(124, 58, 237, 0.1);
            border-radius: 50%;
        }
        .matrix-wallet-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .matrix-wallet-account-number {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
        }
        .matrix-wallet-details-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        .matrix-wallet-detail {
            flex: 1;
        }
        .matrix-wallet-detail-value {
            font-size: 15px;
            font-weight: 600;
        }
        .matrix-wallet-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .matrix-wallet-status.active { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .matrix-wallet-status.inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .matrix-wallet-status.frozen { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .matrix-wallet-copy-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        .matrix-wallet-copy-btn:hover { background: rgba(255,255,255,0.2); }
        .matrix-wallet-instructions {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .matrix-wallet-instructions h4 { color: #166534; margin: 0 0 8px; font-size: 14px; }
        .matrix-wallet-instructions p { color: #15803d; font-size: 13px; margin: 4px 0; }
        .matrix-wallet-instructions ul { margin: 8px 0; padding-left: 20px; }
        .matrix-wallet-instructions li { color: #166534; font-size: 13px; margin: 4px 0; }
        .matrix-create-wallet-intro {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .matrix-create-wallet-intro h3 { color: #1e40af; margin: 0 0 8px; }
        .matrix-create-wallet-intro p { color: #1e40af; font-size: 14px; margin: 4px 0; }
        .matrix-bvn-note {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 8px;
            font-size: 12px;
            color: #92400e;
        }
        </style>
        <?php
    }

    /**
     * Render existing wallet details
     */
    private function render_wallet_details($wallet) {
        ?>
        <div class="matrix-virtual-wallet-card">
            <div class="matrix-wallet-label"><?php _e('Your Virtual Account Number', 'matrix-mlm'); ?></div>
            <div class="matrix-wallet-account-number">
                <?php echo esc_html($wallet->account_number); ?>
                <button class="matrix-wallet-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($wallet->account_number); ?>'); this.textContent='Copied!';">
                    <?php _e('Copy', 'matrix-mlm'); ?>
                </button>
            </div>
            <div class="matrix-wallet-details-row">
                <div class="matrix-wallet-detail">
                    <div class="matrix-wallet-label"><?php _e('Account Name', 'matrix-mlm'); ?></div>
                    <div class="matrix-wallet-detail-value"><?php echo esc_html($wallet->account_name); ?></div>
                </div>
                <div class="matrix-wallet-detail">
                    <div class="matrix-wallet-label"><?php _e('Bank', 'matrix-mlm'); ?></div>
                    <div class="matrix-wallet-detail-value"><?php echo esc_html($wallet->bank_name); ?></div>
                </div>
                <div class="matrix-wallet-detail">
                    <div class="matrix-wallet-label"><?php _e('Currency', 'matrix-mlm'); ?></div>
                    <div class="matrix-wallet-detail-value"><?php echo esc_html($wallet->currency); ?></div>
                </div>
                <div class="matrix-wallet-detail">
                    <div class="matrix-wallet-label"><?php _e('Status', 'matrix-mlm'); ?></div>
                    <div><span class="matrix-wallet-status <?php echo esc_attr($wallet->status); ?>"><?php echo esc_html(ucfirst($wallet->status)); ?></span></div>
                </div>
            </div>
        </div>

        <div class="matrix-wallet-instructions">
            <h4><?php _e('How to Fund Your Wallet', 'matrix-mlm'); ?></h4>
            <p><?php _e('Transfer money to the account number above from any Nigerian bank, and your Matrix wallet will be credited automatically.', 'matrix-mlm'); ?></p>
            <ul>
                <li><?php _e('Use any banking app, USSD code, or ATM to transfer', 'matrix-mlm'); ?></li>
                <li><?php _e('Transfers are credited instantly (usually within seconds)', 'matrix-mlm'); ?></li>
                <li><?php _e('The account number is unique to you and permanent', 'matrix-mlm'); ?></li>
                <li><?php _e('You can share this account number with others to receive payments', 'matrix-mlm'); ?></li>
            </ul>
        </div>

        <div class="matrix-form-card">
            <h3><?php _e('Wallet Information', 'matrix-mlm'); ?></h3>
            <table class="matrix-table">
                <tbody>
                    <tr><td><strong><?php _e('Account Number', 'matrix-mlm'); ?></strong></td><td><code style="font-size: 16px;"><?php echo esc_html($wallet->account_number); ?></code></td></tr>
                    <tr><td><strong><?php _e('Account Name', 'matrix-mlm'); ?></strong></td><td><?php echo esc_html($wallet->account_name); ?></td></tr>
                    <tr><td><strong><?php _e('Bank', 'matrix-mlm'); ?></strong></td><td><?php echo esc_html($wallet->bank_name); ?></td></tr>
                    <tr><td><strong><?php _e('Created', 'matrix-mlm'); ?></strong></td><td><?php echo date('F d, Y g:i A', strtotime($wallet->created_at)); ?></td></tr>
                    <?php if ($wallet->customer_email): ?>
                    <tr><td><strong><?php _e('Linked Email', 'matrix-mlm'); ?></strong></td><td><?php echo esc_html($wallet->customer_email); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($wallet->customer_phone): ?>
                    <tr><td><strong><?php _e('Linked Phone', 'matrix-mlm'); ?></strong></td><td><?php echo esc_html($wallet->customer_phone); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render create wallet form
     */
    private function render_create_form($user, $meta) {
        ?>
        <div class="matrix-create-wallet-intro">
            <h3><?php _e('Get Your Virtual Account Number', 'matrix-mlm'); ?></h3>
            <p><?php _e('Create a dedicated virtual bank account number to receive instant deposits into your Matrix wallet. Anyone can transfer to this account and your wallet balance will be credited automatically.', 'matrix-mlm'); ?></p>
        </div>

        <div class="matrix-form-card">
            <h3><?php _e('Create Virtual Wallet', 'matrix-mlm'); ?></h3>
            <form id="matrix-create-virtual-wallet-form" class="matrix-form">
                <div class="matrix-form-row">
                    <div class="matrix-form-group">
                        <label><?php _e('First Name', 'matrix-mlm'); ?> <span style="color:red;">*</span></label>
                        <input type="text" name="first_name" required value="<?php echo esc_attr(get_user_meta($user->ID, 'first_name', true)); ?>">
                    </div>
                    <div class="matrix-form-group">
                        <label><?php _e('Last Name', 'matrix-mlm'); ?> <span style="color:red;">*</span></label>
                        <input type="text" name="last_name" required value="<?php echo esc_attr(get_user_meta($user->ID, 'last_name', true)); ?>">
                    </div>
                </div>
                <div class="matrix-form-row">
                    <div class="matrix-form-group">
                        <label><?php _e('Email Address', 'matrix-mlm'); ?> <span style="color:red;">*</span></label>
                        <input type="email" name="email" required value="<?php echo esc_attr($user->user_email); ?>">
                    </div>
                    <div class="matrix-form-group">
                        <label><?php _e('Phone Number', 'matrix-mlm'); ?> <span style="color:red;">*</span></label>
                        <input type="tel" name="phone" required value="<?php echo esc_attr($meta->phone ?? ''); ?>" placeholder="08012345678">
                    </div>
                </div>
                <div class="matrix-form-row">
                    <div class="matrix-form-group">
                        <label><?php _e('BVN (Bank Verification Number)', 'matrix-mlm'); ?></label>
                        <input type="text" name="bvn" maxlength="11" pattern="\d{11}" placeholder="<?php _e('11-digit BVN', 'matrix-mlm'); ?>">
                        <div class="matrix-bvn-note"><?php _e('Your BVN is required for KYC verification. It is securely processed and not stored in plain text.', 'matrix-mlm'); ?></div>
                    </div>
                    <div class="matrix-form-group">
                        <label><?php _e('Date of Birth', 'matrix-mlm'); ?></label>
                        <input type="date" name="date_of_birth" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                    </div>
                </div>
                <div class="matrix-form-group">
                    <label><?php _e('Gender', 'matrix-mlm'); ?></label>
                    <select name="gender">
                        <option value=""><?php _e('-- Select --', 'matrix-mlm'); ?></option>
                        <option value="male"><?php _e('Male', 'matrix-mlm'); ?></option>
                        <option value="female"><?php _e('Female', 'matrix-mlm'); ?></option>
                    </select>
                </div>

                <button type="submit" class="matrix-btn matrix-btn-primary matrix-btn-block" id="create-wallet-btn">
                    <?php _e('Generate Virtual Wallet', 'matrix-mlm'); ?>
                </button>
            </form>
        </div>

        <script>
        (function($) {
            'use strict';

            $('#matrix-create-virtual-wallet-form').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const btn = $('#create-wallet-btn');

                if (!confirm('<?php _e("Are you sure you want to create a virtual wallet? This action cannot be undone.", "matrix-mlm"); ?>')) {
                    return;
                }

                btn.prop('disabled', true).text('<?php _e("Creating wallet...", "matrix-mlm"); ?>');

                $.ajax({
                    url: matrixMLM.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'matrix_fintava_create_virtual_wallet',
                        nonce: matrixMLM.nonce,
                        first_name: form.find('[name="first_name"]').val(),
                        last_name: form.find('[name="last_name"]').val(),
                        email: form.find('[name="email"]').val(),
                        phone: form.find('[name="phone"]').val(),
                        bvn: form.find('[name="bvn"]').val(),
                        date_of_birth: form.find('[name="date_of_birth"]').val(),
                        gender: form.find('[name="gender"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message + '\n\nAccount Number: ' + response.data.wallet.account_number + '\nBank: ' + response.data.wallet.bank_name);
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e("Failed to create wallet", "matrix-mlm"); ?>');
                            btn.prop('disabled', false).text('<?php _e("Generate Virtual Wallet", "matrix-mlm"); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e("Network error. Please try again.", "matrix-mlm"); ?>');
                        btn.prop('disabled', false).text('<?php _e("Generate Virtual Wallet", "matrix-mlm"); ?>');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
