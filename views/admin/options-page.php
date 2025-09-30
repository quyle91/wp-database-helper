<?php
// $config available
?>
<div class="wrap">
    <h1>WP DB Helper - Builder</h1>
    <form method="post">
        <?php wp_nonce_field('wpdh_options_save'); ?>

        <textarea name="wpdh_options[json]" style="width:100%;height:200px;"><?php echo esc_textarea(json_encode($config)); ?></textarea>

        <p><button class="button button-primary" type="submit">Save</button></p>
    </form>
</div>