/* global Stripe, s4wc_info */

// Set API key
Stripe.setPublishableKey( s4wc_info.publishableKey );

jQuery( function ( $ ) {
    var $form = $( 'form.checkout, form#order_review' ),
        savedFieldValues = {},
        $ccForm, $ccNumber, $ccExpiry, $ccCvc;

    function initCCForm () {
        $ccForm   = $( '#s4wc-cc-form' );
        $ccNumber = $ccForm.find( '#s4wc-card-number' );
        $ccExpiry = $ccForm.find( '#s4wc-card-expiry' );
        $ccCvc    = $ccForm.find( '#s4wc-card-cvc' );

        // Hide the CC form if the user has a saved card.
        if ( s4wc_info.hasCard && s4wc_info.savedCardsEnabled ) {
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

        // Add in lost data
        if ( savedFieldValues.number ) {
            $ccNumber.val( savedFieldValues.number.val ).attr( 'class', savedFieldValues.number.classes );
        }

        if ( savedFieldValues.expiry ) {
            $ccExpiry.val( savedFieldValues.expiry.val );
        }

        if ( savedFieldValues.cvc ) {
            $ccCvc.val( savedFieldValues.cvc.val );
        }
    }

    function stripeFormHandler () {
        if ( $( '#payment_method_s4wc' ).is( ':checked' ) && ( ! $( 'input[name="s4wc_card"]' ).length || $( 'input[name="s4wc_card"]:checked' ).val() === 'new' ) ) {

            if ( ! $( 'input.stripe_token' ).length ) {
                var cardExpiry = $ccExpiry.payment( 'cardExpiryVal' ),
                    name = ( $( '#billing_first_name' ).val() || $( '#billing_last_name' ).val() ) ? $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val() : s4wc_info.billing_name;

                var stripeData = {
                    number          : $ccNumber.val() || '',
                    cvc             : $ccCvc.val() || '',
                    exp_month       : cardExpiry.month || '',
                    exp_year        : cardExpiry.year || '',
                    name            : $( '.s4wc-billing-name' ).val() || name || '',
                    address_line1   : $( '#billing_address_1' ).val() || s4wc_info.billing_address_1 || '',
                    address_line2   : $( '#billing_address_2' ).val() || s4wc_info.billing_address_2 || '',
                    address_city    : $( '#billing_city' ).val() || s4wc_info.billing_city || '',
                    address_state   : $( '#billing_state' ).val() || s4wc_info.billing_state || '',
                    address_zip     : $( '.s4wc-billing-zip' ).val() || $( '#billing_postcode' ).val() || s4wc_info.billing_postcode || '',
                    address_country : $( '#billing_country' ).val() || s4wc_info.billing_country || ''
                };

                // Validate form fields, create token if form is valid
                if ( stripeFormValidator( stripeData ) ) {
                    Stripe.createToken( stripeData, stripeResponseHandler );
                    return false;
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

        } else {
            // insert the token into the form so it gets submitted to the server
            $form.append( '<input type="hidden" class="stripe_token" name="stripe_token" value="' + response.id + '"/>' );

            // tell the server if we want to save the card
            var $ccSave = $( '#s4wc-cc-form #s4wc-save-card' ).prop('checked');
            $form.append( '<input type="hidden" class="save_card" name="save_card" value="' + $ccSave + '"/>' );
            $form.submit();
        }
    }

    function stripeFormValidator ( stripeData ) {

        // Validate form fields
        var errors = fieldValidator( stripeData );

        // If there are errors, display them using wc_add_notice on the backend
        if ( errors.length ) {

            $( '.stripe_token, .form_errors' ).remove();

            for ( var i = 0, len = errors.length; i < len; i++ ) {
                var field = errors[i].field,
                    type  = errors[i].type;

                $form.append( '<input type="hidden" class="form_errors" name="' + field + '" value="' + type + '">' );
            }

            $form.append( '<input type="hidden" class="form_errors" name="form_errors" value="1">' );

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
        var errors = [];

        // Card number validation
        if ( ! stripeData.number ) {
            errors.push({
                'field' : 's4wc-card-number',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardNumber( stripeData.number ) ) {
            errors.push({
                'field' : 's4wc-card-number',
                'type'  : 'invalid'
            });
        }

        // Card expiration validation
        if ( ! stripeData.exp_month || ! stripeData.exp_year ) {
            errors.push({
                'field' : 's4wc-card-expiry',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardExpiry( stripeData.exp_month, stripeData.exp_year ) ) {
            errors.push({
                'field' : 's4wc-card-expiry',
                'type'  : 'invalid'
            });
        }

        // Card CVC validation
        if ( ! stripeData.cvc ) {
            errors.push({
                'field' : 's4wc-card-cvc',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardCVC( stripeData.cvc, $.payment.cardType( stripeData.number ) ) ) {
            errors.push({
                'field' : 's4wc-card-cvc',
                'type'  : 'invalid'
            });
        }

        // Send the errors back
        return errors;
    }

    // Make sure the credit card form exists before we try working with it
    $( 'body' ).on( 'updated_checkout.s4wc', initCCForm ).trigger( 'updated_checkout.s4wc' );

    // Checkout Form
    $( 'form.checkout' ).on( 'checkout_place_order', stripeFormHandler );

    // Pay Page Form
    $( 'form#order_review' ).on( 'submit', stripeFormHandler );

    // Both Forms
    $form.on( 'keyup change', '#s4wc-card-number, #s4wc-card-expiry, #s4wc-card-cvc, input[name="s4wc_card"], input[name="payment_method"]', function () {

        // Save credit card details in case the address changes (or something else)
        savedFieldValues.number = {
            'val'     : $ccNumber.val(),
            'classes' : $ccNumber.attr( 'class' )
        };
        savedFieldValues.expiry = {
            'val' : $ccExpiry.val()
        };
        savedFieldValues.cvc = {
            'val' : $ccCvc.val()
        };

        $( '.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message, .stripe_token, .form_errors' ).remove();
    });
});
