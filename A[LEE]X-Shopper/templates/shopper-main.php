<?php
global $alx_shopper_current_filter_config, $alx_shopper_current_filter_id;
include plugin_dir_path(__FILE__) . 'shopper-form.php';
?>

<!-- Optional: Quick view modal (if used globally) -->
<div id="alx-quick-view-modal" style="display:none;">
    <div class="alx-quick-view-content">
        <span class="alx-quick-view-close">&times;</span>
        <div class="alx-quick-view-body"></div>
    </div>
</div>

<div class="alx-shopper-results-box"></div>

<?php
// Only show the email box if enabled for this filter config (CPT)
if (
    isset($alx_shopper_current_filter_config['enable_email_results']) &&
    $alx_shopper_current_filter_config['enable_email_results']
):
?>
<div class="alx-shopper-email-box">
    <h3>Email Your Matches</h3>
    <input type="email" id="alx-email-input" placeholder="Your email address">
    <button id="alx-send-email">Send Results</button>
    <div class="alx-shopper-email-status"></div>
</div>
<?php endif; ?>
