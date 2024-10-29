( function( $ ) {
    $( document ).ready(
        function() {

            if ( $( 'textarea#bayarcash_portal_token' ).length < 1 ) {
                return;
            }

            const set_status = function( text, status ) {
                let $target = $( 'div#bayarcash-verfiy-token' ).find( 'span#verify-status' );
                $target.removeClass( 'valid invalid' );
                if ( status === 1 ) {
                    text += ' <span class="dashicons dashicons-yes-alt"></span>';
                    $target.addClass( 'valid' );
                } else if ( status === 0 ) {
                    text += ' <span class="dashicons dashicons-dismiss"></span>';
                    $target.addClass( 'invalid' );
                }

                $target.html( text );
            };

            const verfiy_token = function( is_reset = false ) {
                if ( "undefined" === typeof( bayarcash_givewp_config ) ) {
                    console.log( 'bayarcash_givewp_config not defined' );
                    return false;
                }

                let config = bayarcash_givewp_config;
                let token = $.trim( $( 'textarea#bayarcash_portal_token' ).val() ) || '';
                if ( token === '' ) {
                    $( 'div#bayarcash-verfiy-token' ).find( 'span#verify-status' ).empty();
                    if ( is_reset ) {
                        set_status( 'Please insert Token.', 0 );
                    }
                    return false;
                }

                $.ajax( {
                    url: config.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    cache: false,
                    data: {
                        "action": "bayarcash_givewp_script",
                        "token": config.token,
                        "type": 'verify',
                        'bayarcash_portal_token': token,
                        'reset': is_reset,
                        'nocache': ( new Date().getTime() ),
                    },
                    beforeSend: function() {
                        set_status( 'Validating PAT token..' );
                    },
                    success: function( data ) {
                        if ( data.is_verified ) {
                            set_status( 'PAT Token is valid', 1 );
                            return;
                        }

                        set_status( 'Invalid PAT Token', 0 );
                    },
                    error: function( data, textStatus, jqXHR ) {
                        console.log( jqXHR );
                        set_status( 'Failed to validate PAT Token', 0 );
                    }
                } );
            };

            setTimeout( verfiy_token, 300 );

            $( 'div#bayarcash-verfiy-token' ).find( '#verify-button' ).on(
                'click',
                function( e ) {
                    e.preventDefault();
                    verfiy_token( true );
                }
            );
        }
    );

} )( jQuery );