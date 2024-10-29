<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */
\defined('ABSPATH') && \function_exists('Give') && !empty($form_id) || exit;

$bayarcash_phone               = isset($_REQUEST['bayarcash_phone']) ? sanitize_text_field($_REQUEST['bayarcash_phone']) : '';
$bayarcash_identification_type = isset($_REQUEST['bayarcash_identification_type']) ? sanitize_text_field($_REQUEST['bayarcash_identification_type']) : '1';
$bayarcash_identification_id   = isset($_REQUEST['bayarcash_identification_id']) ? sanitize_text_field($_REQUEST['bayarcash_identification_id']) : '';
?>
<style id="bayarcash-form-fields">
    .bayarcash-hidden {
        display: none;
    }

    <?php if ( !$is_legacy_form) : ?>.give-personal-info-section #bayarcash-phone-wrap label,
    .give-personal-info-section #bayarcash-identification-type-wrap label,
    .give-personal-info-section #bayarcash-identification-id-wrap label {
        clip: rect(0, 0, 0, 0);
        border-width: 0;
        height: 1px;
        margin: -1px;
        overflow: hidden;
        padding: 0;
        position: absolute;
        white-space: nowrap;
        width: 1px
    }

    #bayarcash-phone-wrap,
    #bayarcash-identification-type-wrap,
    #bayarcash-identification-id-wrap {
        position: relative
    }

    #bayarcash-phone-wrap:before,
    #bayarcash-identification-id-wrap:before {
        block-size: 1em;
        color: #8d8e8e;
        font-family: Font Awesome\ 5 Free;
        font-weight: 900;
        inset-block-end: .0em;
        inset-block-start: 0;
        inset-inline-start: 0.7rem;
        margin-block: auto;
        pointer-events: none;
        position: absolute;
        font-size: 14px;
    }

    #bayarcash-phone-wrap input,
    #bayarcash-identification-id-wrap input {
        -webkit-padding-start: 2.6875rem;
        padding-inline-start: 2.6875rem
    }

    #bayarcash-phone-wrap:before {
        transform: rotate(90deg);
        content: "\f095";
    }

    #bayarcash-identification-id-wrap:before {
        content: "\f2c2";
    }

    #bayarcash-phone-wrap input#bayarcash-phone,
    #bayarcash-identification-id-wrap input#bayarcash-identification-id {
        padding-left: 33px !important;
    }

    <?php endif;
    ?>

</style>

<?php if (true === $bayarcash_enable_phone_number) : ?>
<script id="bayarcash-form-fields-phone-enable">
    let bayarcash_enable_phone_number = true;

</script>
<?php else : ?>
<script id="bayarcash-form-fields-phone-disable">
    let bayarcash_enable_phone_number = false;

</script>
<?php endif; ?>

<script id="bayarcash-form-fields">
    ( function( $ ) {
        $( document ).ready( function() {
            $( 'input[name=payment-mode]' ).on( 'click', function() {
                let $val = $( this ).val();
                if ( 'bayarcash' === $val ) {
                    if ( $( 'input[name=give-recurring-period]' ).is( ':checked' ) || $( 'input[name=_give_is_donation_recurring]' ).val() === '1' ) {
                        setTimeout( () => {
                            $( 'p[id^=bayarcash-]' ).removeClass( 'bayarcash-hidden' );
                        }, 750 );
                    }
                } else {
                    $( 'p[id^=bayarcash-]' ).addClass( 'bayarcash-hidden' );
                }
            } );

            $( 'input[name=give-recurring-period]' ).on( 'click', function() {
                if ( $( this ).is( ':checked' ) && $( 'input[id^=give-gateway-bayarcash]' ).is( ':checked' ) ) {
                    $( 'p[id^=bayarcash-]' ).removeClass( 'bayarcash-hidden' );
                } else {
                    $( 'p[id^=bayarcash-]' ).addClass( 'bayarcash-hidden' );
                }
            } );

            if ( $( 'input[name=_give_is_donation_recurring]' ).val() === '1' && $( 'input[id^=give-gateway-bayarcash]' ).is( ':checked' ) ) {
                $( 'p[id^=bayarcash-]' ).removeClass( 'bayarcash-hidden' );
            }

            if ( bayarcash_enable_phone_number ) {
                $( 'p[id=bayarcash-phone-wrap]' ).removeClass( 'bayarcash-hidden' );
            }

        } );
    } )( jQuery );

</script>

<?php if (!empty($_GET['bc-select-recurring'])) : ?>
<script id="bayarcash-form-fields-reload">
    ( function( $ ) {
        $( document ).ready( function() {
            setTimeout( function() {
                $( 'input[name=give-recurring-period][data-period]' ).filter( function() {
                    let $self = $( this );
                    let period = $( this ).attr( 'data-period' );
                    if ( period === 'month' || period === 'week' ) {
                        $self.trigger( 'click' );
                    }
                } );

                $( 'p[id^=bayarcash-]' ).removeClass( 'bayarcash-hidden' );

                if ( bayarcash_enable_phone_number ) {
                    $( 'p[id=bayarcash-phone-wrap]' ).removeClass( 'bayarcash-hidden' );
                }

            }, 750 );
        } );
    } )( jQuery );

</script>
<?php endif; ?>

<p id="bayarcash-phone-wrap" class="form-row form-row-wide bayarcash-hidden">
    <label class="give-label" for="bayarcash-phone">
        <?php if ($is_legacy_form) : ?>
        <?php esc_html_e('Phone Number', 'bayarcash-givewp'); ?>
        <span class="give-required-indicator">*</span>
        <?php echo Give()->tooltips->render_help(esc_html__('We require a phone number for verification.', 'bayarcash-givewp')); ?>
        <?php endif; ?>
    </label>
    <input class="give-input" type="text" name="bayarcash_phone" autocomplete="bayarcash_phone" placeholder="<?php esc_html_e('Phone Number', 'bayarcash-givewp'); ?>" id="bayarcash-phone" value="<?php echo esc_attr($bayarcash_phone); ?>" aria-required="false">
</p>

<p id="bayarcash-identification-type-wrap" class="form-row form-row-first form-row-responsive bayarcash-hidden">
    <label class="give-label" for="bayarcash-identification-type">
        <?php if ($is_legacy_form) : ?>
        <?php esc_html_e('Identification Type', 'bayarcash-givewp'); ?>
        <span class="give-required-indicator">*</span>
        <?php echo Give()->tooltips->render_help(esc_html__('We require a identification type for verification.', 'bayarcash-givewp')); ?>
        <?php endif; ?>
    </label>
    <select class="give-select" id='bayarcash-identification-type' name='bayarcash_identification_type'>
        <?php
    foreach ([
        '1' => 'New IC Number',
        '2' => 'Old IC Number',
        '3' => 'Passport Number',
        '4' => 'Business Registration',
    ] as $id => $vl) :
        ?>
        <option value="<?php echo esc_attr($id); ?>" <?php echo esc_html((int) $id === (int) $bayarcash_identification_type ? ' selected' : ''); ?>><?php echo esc_html($vl); ?></option>
        <?php endforeach; ?>
    </select>
</p>
<p id="bayarcash-identification-id-wrap" class="form-row form-row-last form-row-responsive bayarcash-hidden">
    <label class="give-label" for="bayarcash-identification-id">
        <?php if ($is_legacy_form) : ?>
        <?php esc_html_e('Identification Number', 'bayarcash-givewp'); ?>
        <span class="give-required-indicator">*</span>
        <?php echo Give()->tooltips->render_help(esc_html__('We require a identification number for verification.', 'bayarcash-givewp')); ?>
        <?php endif; ?>
    </label>
    <input class="give-input" type="text" name="bayarcash_identification_id" autocomplete="" placeholder="<?php esc_html_e('Identification Number', 'bayarcash-givewp'); ?>" id="bayarcash-identification-id" value="<?php echo esc_attr($bayarcash_identification_id); ?>" maxlength="27" aria-required="false">
</p>
<?php
wp_nonce_field('bayarcash_givewp_form_fields', '_bayarcash_givewp_nonce');
