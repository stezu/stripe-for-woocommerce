// Set API key
Stripe.setPublishableKey( wc_stripe_info.publishableKey );

jQuery(function ($) {
    var $form = $( 'form.checkout, form#order_review' ),
        $ccForm = $( '#wc_stripe-creditcard-form' );

    // Add container for card image
    $( '.wc_stripe-card-number' ).after( '<span class="wc_stripe-card-image"></span>' );

    // Hide the CC form if the user has a saved card.
    if ( wc_stripe_info.hasCard ) {
        $ccForm.hide();
    }

    // Checkout Form
    $( 'form.checkout' ).on( 'checkout_place_order_wc_stripe', function () {
        return stripeFormHandler();
    });

    // Pay Page Form
    $( 'form#order_review' ).on( 'submit', function () {
        return stripeFormHandler();
    });

    // Both Forms
    $form.on( 'keyup change', '#card-number, #card-expiry, #card-cvc, input[name="wc_stripe_card"]', function () {
        $( '.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message, .stripe_token, .form_errors' ).remove();
    });

    // Toggle new card form
    $form.on( 'change', 'input[name="wc_stripe_card"]', function () {

        if ( $( 'input[name="wc_stripe_card"]:checked' ).val() === 'new' ) {
            $ccForm.slideDown( 200 );
        } else {
            $ccForm.slideUp( 200 );
        }
    });

    function stripeFormHandler () {
        if ( $( '#payment_method_wc_stripe' ).is( ':checked' ) && ( ! $( 'input[name="wc_stripe_card"]' ).length || $( 'input[name="wc_stripe_card"]:checked' ).val() === 'new' ) ) {

            if ( ! $( 'input.stripe_token' ).length ) {
                var cardExpiry = $('.wc_stripe-card-expiry').payment('cardExpiryVal');

                var stripeData = {
                    number          : $( '.wc_stripe-card-number' ).val(),
                    cvc             : $( '.wc_stripe-card-cvc' ).val(),
                    exp_month       : cardExpiry.month,
                    exp_year        : cardExpiry.year,
                    name            : $( '.wc_stripe-billing-name' ).val() || $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val(),
                    address_line1   : $( '#billing_address_1' ).val(),
                    address_line2   : $( '#billing_address_2' ).val(),
                    address_state   : $( '#billing_state' ).val(),
                    address_zip     : $( '.wc_stripe-billing-zip' ).val() || $( '#billing_postcode' ).val(),
                    address_country : $( '#billing_country' ).val()
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
            $ccForm.append( '<input type="hidden" class="stripe_token" name="stripe_token" value="' + response.id + '"/>' );
            $form.submit();
        }
    }

    function stripeFormValidator ( stripeData ) {

        // Validate form fields
        var message = fieldValidator( stripeData );

        // Action that we trigger
        message.action = 'stripe_form_validation';

        // If there are errors, display them using wc_add_notice on the backend
        if ( message.errors.length ) {
            $.post( wc_stripe_info.ajaxurl, message, function ( code ) {
                if ( code.indexOf( '<!--WC_STRIPE_START-->' ) >= 0 ) {
                    code = code.split( '<!--WC_STRIPE_START-->' )[1]; // Strip off anything before WC_STRIPE_START
                }
                if ( code.indexOf( '<!--WC_STRIPE_END-->' ) >= 0 ) {
                    code = code.split( '<!--WC_STRIPE_END-->' )[0]; // Strip off anything after WC_STRIPE_END
                }
                var result = $.parseJSON( code );

                // Clear out event handlers to make sure they only fire once.
                $( 'body' ).off( '.wc_stripe' );

                // Add new errors if errors already exist
                $( 'body' ).on( 'checkout_error.wc_stripe', function () {

                    if ( result.messages.indexOf( '<ul class="woocommerce-error">' ) >= 0 ) {
                        result.messages = result.messages.split( '<ul class="woocommerce-error">' )[1]; // Strip off anything before ul.woocommerce-error
                    }
                    if ( result.messages.indexOf( '</ul>' ) >= 0 ) {
                        result.messages = result.messages.split( '</ul>' )[0]; // Strip off anything after ul.woocommerce-error
                    }

                    $( '.woocommerce-error' ).append( result.messages );
                });

                // Add errors the normal way
                $( '.woocommerce-error' ).remove();
                $form.prepend( result.messages );
            });

            $( '.stripe_token, .form_errors' ).remove();
            $ccForm.append( '<input type="hidden" class="form_errors" name="form_errors" value="1">' );

            $form.unblock();

            return false;
        }

        // Create the token if we don't have any errors
        else {
            var clearErrors = {
                'errors': []
            };
            // Clear out notices
            $.post( wc_stripe_info.ajaxurl, clearErrors );
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
                'field': 'number',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardNumber( stripeData.number ) ) {
            message.errors.push({
                'field': 'number',
                'type': 'invalid'
            });
        }

        // Card expiration validation
        if ( ! stripeData.exp_month || ! stripeData.exp_year ) {
            message.errors.push({
                'field': 'expiration',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardExpiry( stripeData.exp_month, stripeData.exp_year ) ) {
            message.errors.push({
                'field': 'expiration',
                'type': 'invalid'
            });
        }

        // Card CVC validation
        if ( ! stripeData.cvc ) {
            message.errors.push({
                'field': 'cvc',
                'type': 'undefined'
            });
        } else if ( ! $.payment.validateCardCVC( stripeData.cvc, $.payment.cardType( stripeData.number ) ) ) {
            message.errors.push({
                'field': 'cvc',
                'type': 'invalid'
            });
        }

        // Send the message back
        return message;
    }

    $( 'body' ).on( 'updated_checkout', function() {
        $( '.wc_stripe-card-number' ).payment( 'formatCardNumber' );
        $( '.wc_stripe-card-expiry' ).payment( 'formatCardExpiry' );
        $( '.wc_stripe-card-cvc' ).payment( 'formatCardCVC' );
    });

    $( '.wc_stripe-card-number' ).payment( 'formatCardNumber' );
    $( '.wc_stripe-card-expiry' ).payment( 'formatCardExpiry' );
    $( '.wc_stripe-card-cvc' )
        .payment( 'formatCardCVC' )
        .focus( function () {
            $( '.wc_stripe-card-number' ).addClass( 'cvc' );
        })
        .blur( function () {
            $( '.wc_stripe-card-number' ).removeClass( 'cvc' );
        });
});