// Set API key
Stripe.setPublishableKey( s4wc_info.publishableKey );

jQuery( function ( $ ) {
    var $body = $( 'body' ),
        $form = $( 'form.checkout, form#order_review' ),
        $ccForm;

    // Make sure the form doesn't use html validation
    $form.attr('novalidate', 'novalidate');

    // Make sure the credit card form exists before we try working with it
    $(window).on( 'load.s4wc', function() {
        initCCForm();
    });
    $body.on( 'updated_checkout.s4wc', function () {
        initCCForm();
    });

    // Checkout Form
    $( 'form.checkout' ).on( 'checkout_place_order_s4wc', function () {
        return stripeFormHandler();
    });

    // Pay Page Form
    $( 'form#order_review' ).on( 'submit', function () {
        return stripeFormHandler();
    });

    // Both Forms
    $form.on( 'keyup change', '#card-number, #card-expiry, #card-cvc, input[name="s4wc_card"]', function () {
        $( '.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message, .stripe_token, .form_errors' ).remove();
    });

    function initCCForm() {
        $ccForm = $( '#s4wc-creditcard-form' );

        // Hide the CC form if the user has a saved card.
        if ( s4wc_info.hasCard ) {
            $ccForm.hide();
        }

        // Toggle new card form
        $form.on( 'change', 'input[name="s4wc_card"]', function () {

            if ( $( 'input[name="s4wc_card"]:checked' ).val() === 'new' ) {
                $ccForm.slideDown( 200 );
            } else {
                $ccForm.slideUp( 200 );
            }
        });

        $( '.s4wc-card-number' )
            .payment( 'formatCardNumber' )
            .after( '<span class="s4wc-card-image"></span>' );
        $( '.s4wc-card-expiry' ).payment( 'formatCardExpiry' );
        $( '.s4wc-card-cvc' )
            .payment( 'formatCardCVC' )
            .focus( function () {
                $( '.s4wc-card-number' ).addClass( 'cvc' );
            })
            .blur( function () {
                $( '.s4wc-card-number' ).removeClass( 'cvc' );
            });
    }

    function stripeFormHandler () {
        if ( $( '#payment_method_s4wc' ).is( ':checked' ) && ( ! $( 'input[name="s4wc_card"]' ).length || $( 'input[name="s4wc_card"]:checked' ).val() === 'new' ) ) {

            if ( ! $( 'input.stripe_token' ).length ) {
                var cardExpiry = $( '.s4wc-card-expiry' ).payment( 'cardExpiryVal' ),
                    name = ( s4wc_info.billing_first_name || s4wc_info.billing_last_name ) ? s4wc_info.billing_first_name + ' ' + s4wc_info.billing_last_name : $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val();

                var stripeData = {
                    number          : $( '.s4wc-card-number' ).val() || '',
                    cvc             : $( '.s4wc-card-cvc' ).val() || '',
                    exp_month       : cardExpiry.month || '',
                    exp_year        : cardExpiry.year || '',
                    name            : $( '.s4wc-billing-name' ).val() || name,
                    address_line1   : s4wc_info.billing_address_1 || $( '#billing_address_1' ).val() || '',
                    address_line2   : s4wc_info.billing_address_2 || $( '#billing_address_2' ).val() || '',
                    address_city    : s4wc_info.billing_city || $('#billing_city').val() || '',
                    address_state   : s4wc_info.billing_state || $( '#billing_state' ).val() || '',
                    address_zip     : s4wc_info.billing_postcode || $( '.s4wc-billing-zip' ).val() || $( '#billing_postcode' ).val() || '',
                    address_country : s4wc_info.billing_country || $( '#billing_country' ).val() || ''
                };

                $form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff url(' + woocommerce_params.ajax_loader_url + ') no-repeat center',
                        opacity: 0.6
                    }
                });

                // Validate form fields, create token if form is valid
                if ( stripeFormValidator( stripeData ) ) {
                    Stripe.createToken( stripeData, stripeResponseHandler );
                    return false;
                } else {
                    return true;
                }
            }
        }

        return true;
    }

    function stripeResponseHandler ( status, response ) {

        if ( response.error ) {
            // show the errors on the form
            $( '.payment-errors, .stripe_token, .form_errors' ).remove();
            $ccForm.before( '<span class="payment-errors required">' + response.error.message + '</span>' );
            $form.unblock();

        } else {
            // insert the token into the form so it gets submitted to the server
            $form.append( '<input type="hidden" class="stripe_token" name="stripe_token" value="' + response.id + '"/>' );
            $form.submit();
        }
    }

    function stripeFormValidator ( stripeData ) {

        // Validate form fields
        var message = fieldValidator( stripeData );

        // If there are errors, display them using wc_add_notice on the backend
        if ( message.errors.length ) {

            $( '.stripe_token, .form_errors' ).remove();

            for ( var i = 0, len = message.errors.length; i < len; i++ ) {
                var field = message.errors[i].field,
                    type = message.errors[i].type;

                $form.append( '<input type="hidden" class="form_errors" name="' + field + '" value="' + type + '">' );
            }

            $form.append( '<input type="hidden" class="form_errors" name="form_errors" value="1">' );

            $form.unblock();

            return false;
        }

        // Create the token if we don't have any errors
        else {
            // Clear out notices
            $form.find( '.woocommerce-error' ).remove();

            return true;
        }
    }

    function fieldValidator ( stripeData ) {
        var message = {
            'errors': []
        };

        // Card number validation
        if ( ! stripeData.number ) {
            message.errors.push({
                'field': 's4wc-card-number',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardNumber( stripeData.number ) ) {
            message.errors.push({
                'field': 's4wc-card-number',
                'type': 'invalid'
            });
        }

        // Card expiration validation
        if ( ! stripeData.exp_month || ! stripeData.exp_year ) {
            message.errors.push({
                'field': 's4wc-card-expiry',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardExpiry( stripeData.exp_month, stripeData.exp_year ) ) {
            message.errors.push({
                'field': 's4wc-card-expiry',
                'type': 'invalid'
            });
        }

        // Card CVC validation
        if ( ! stripeData.cvc ) {
            message.errors.push({
                'field': 's4wc-card-cvc',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardCVC( stripeData.cvc, $.payment.cardType( stripeData.number ) ) ) {
            message.errors.push({
                'field': 's4wc-card-cvc',
                'type': 'invalid'
            });
        }

        // Send the message back
        return message;
    }
});