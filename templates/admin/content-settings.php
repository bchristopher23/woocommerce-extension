<?php 

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) die;
?>

<style>
    
    .settings-heading {
        margin-bottom: 15px !important;
    }

    .spro-input {
        min-width: 475px;
    }

    .spro-input.checked {
        border: 1px solid green;
    }

    .spro-input.not-checked {
        border: 1px solid red;
    }

    .connection-buttons {
        display: flex;
        margin-top: 30px;
    }

    .connection-buttons .submit {
        margin: 0;
        padding: 0;
    }

    .connection-buttons .action {
        margin-right: 10px;
    }

    .connection-message {
        margin-top: 15px;
        font-size: 18px;
    }

    .connection-message .name {
        text-transform: capitalize;
    }

    .connection-message.success {
        color: green;
    }

    .connection-message.fail {
        color: red;
    }

    fieldset {
        margin-bottom: 15px;
    }

    fieldset label {
        display: block;
        margin-bottom: 5px;
    }

</style>

<div class="wrap">

    <h2 class="settings-heading">Subscribe Pro <?php esc_attr_e('Options', 'spro' ); ?></h2>

    <form method="post" name="spro_settings" action="options.php">
        <?php

        // Grab all options
        $payment_method = get_option( 'spro_settings_payment_method' );
        $base_url = get_option( 'spro_settings_base_url' );
        $client_id = get_option( 'spro_settings_client_id' );
        $client_secret = get_option( 'spro_settings_client_secret' );
        $callback_secret = get_option( 'spro_settings_callback_secret' );
        $subscriptions_widget_url = get_option( 'spro_settings_subscriptions_widget_url' );
        $subscriptions_widget_config = get_option( 'spro_settings_subscriptions_widget_config' );

        ?>

        <fieldset>
            <label for="payment_method"><?php esc_attr_e( 'Payment Method', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Payment Method', 'spro' ); ?></span>
            </legend>
            <select name="payment_method" id="payment_method" class="spro-input">
                <option value="">Select Payment Method</option>
                <option value="anet" <?php echo $payment_method == 'anet' ? 'selected' : ''; ?>>Authorize.Net</option>
                <option value="ebiz" <?php echo $payment_method == 'ebiz' ? 'selected' : ''; ?>>eBizCharge</option>
            </select>
        </fieldset>

        <fieldset>
            <label for="base_url"><?php esc_attr_e( 'Base URL', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Base URL', 'spro' ); ?></span>
            </legend>
            <input type="text" class="spro-input" id="base_url" name="base_url" value="<?php if( ! empty( $base_url ) ) echo $base_url; else echo 'https://api.subscribepro.com'; ?>"/>
        </fieldset>

        <fieldset>
            <label for="client_id"><?php esc_attr_e( 'Client ID', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Client ID', 'spro' ); ?></span>
            </legend>
            <input type="text" class="spro-input" id="client_id" name="client_id" value="<?php if( ! empty( $client_id ) ) echo $client_id; else echo ''; ?>"/>
        </fieldset>
        
        <fieldset>
            <label for="client_secret"><?php esc_attr_e( 'Client Secret', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Client Secret', 'spro' ); ?></span>
            </legend>
            <input type="password" class="spro-input" id="client_secret" name="client_secret" value="<?php if( ! empty( $client_secret ) ) echo $client_secret; else echo ''; ?>"/>
        </fieldset>
        
        <fieldset>
            <label for="callback_secret"><?php esc_attr_e( 'Order Callback Shared Secret', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Order Callback Shared Secret', 'spro' ); ?></span>
            </legend>
            <input type="password" class="spro-input" id="callback_secret" name="callback_secret" value="<?php if( ! empty( $callback_secret ) ) echo $callback_secret; else echo ''; ?>"/>
        </fieldset>
        
        <fieldset>
            <label for="subscriptions_widget_url"><?php esc_attr_e( 'Hosted My Subscriptions Page Widget Source URL', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Hosted My Subscriptions Page Widget Source URL', 'spro' ); ?></span>
            </legend>
            <input type="text" class="spro-input" id="subscriptions_widget_url" name="subscriptions_widget_url" value="<?php if( ! empty( $subscriptions_widget_url ) ) echo $subscriptions_widget_url; else echo ''; ?>"/>
        </fieldset>
        
        <fieldset>
            <label for="subscriptions_widget_config"><?php esc_attr_e( 'Hosted My Subscriptions Page Configuration', 'spro' ); ?></label>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Hosted My Subscriptions Page Configuration', 'spro' ); ?></span>
            </legend>
            <textarea class="spro-input" rows="6" id="subscriptions_widget_config" name="subscriptions_widget_config"><?php if( ! empty( $subscriptions_widget_config ) ) echo str_replace( '\\', '', $subscriptions_widget_config ) ; else echo ''; ?></textarea>
        </fieldset>

        <div class="connection-buttons">
            <a href="#" class="button button-primary test-con-btn">Test Connection</a>
        </div>

        <div class="connection-message"></div>


    </form>

    <script type="text/javascript">

        jQuery(function($) {

            jQuery('.spro-input').each(function() {

                if ( jQuery(this).val() != '' ) {
                    $(this).addClass('checked');
                } else {
                    $(this).addClass('not-checked');
                }

            });

            // Save Credentials on Focus Out
            jQuery('.spro-input').change(function() {

                var val = $(this).val(),
                    name = $(this).attr('name');

                if ( val != '' ) {
                    $(this).addClass('checked');
                    $(this).removeClass('not-checked');
                } else {
                    $(this).addClass('not-checked');
                    $(this).removeClass('checked');
                }

                // Make AJAX Request
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    data: { action: 'save_connection_credentials', 'val': val, 'name': name },
                    success: function(response) {

                        var data = jQuery.parseJSON(response.data);

                        var name = data.name,
                        label = jQuery( 'label[for="' + name + '"]' ).contents().get(0).nodeValue;

                        jQuery('.connection-message').addClass('success').removeClass('fail').show();
                        jQuery('.connection-message').html( '<span class="name">' + label + '</span> was saved!');
                    
                    }
                });

            });

            // Test Connection Click
            jQuery('.test-con-btn').click(function(e) {

                e.preventDefault();

                jQuery(this).text('Testing....');

                // Make AJAX Request
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    data: { action: 'test_connection' },
                    success: function(response) {

                        console.log('response');
                        console.log(response);

                        if (response.data == 'success') {
                            jQuery('.connection-message').addClass('success').removeClass('fail');
                            jQuery('.connection-message').text('Connection successful!');
                        } else {
                            jQuery('.connection-message').addClass('fail').removeClass('success');
                            jQuery('.connection-message').text('Connection failed!');
                        }

                        jQuery('.test-con-btn').text('Test Connection');
                    
                    }
                });

            });

        });

    </script>

</div>