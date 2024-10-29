<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */
\defined('ABSPATH') && \function_exists('Give') || exit;
$phone_numbers = $donor->get_meta('give_bayarcash_phone', false);
?>
<div class="donor-section clear">
    <h3><?php esc_html_e('Phone Numbers', 'bayarcash-givewp'); ?></h3>

    <div class="postbox">
        <div class="inside">
            <?php if (empty($phone_numbers)) : ?>
            <p><?php esc_html_e('This donor does not have any phone number saved.', 'bayarcash-givewp'); ?></p>
            <?php else : ?>
            <?php foreach ($phone_numbers as $phone_number) : ?>
            <p><?php echo esc_html($phone_number); ?></p>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
