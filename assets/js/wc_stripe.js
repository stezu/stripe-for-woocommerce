// Set API key
Stripe.setPublishableKey(wc_stripe_info.publishableKey);

jQuery(function ($) {
    var $form = $('form.checkout, form#order_review'),
        $ccForm = $('#wc_stripe-creditcard-form');

    // Hide the CC form if the user has a saved card.
    if ( wc_stripe_info.hasCard ) {
        $ccForm.hide();
    }

    // Checkout Form
    $('form.checkout').on('checkout_place_order', function () {
        return stripeFormHandler();
    });

    // Pay Page Form
    $('form#order_review').on('submit', function () {
        return stripeFormHandler();
    });

    // Both Forms
    $form.on('change', '#card-number, #card-expiry, #card-cvc, input[name="wc_stripe_card"]', function () {
        $('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message, .stripe_token').remove();
    });

    // Toggle new card form
    $form.on('change', 'input[name="wc_stripe_card"]', function () {

        if ( $('input[name="wc_stripe_card"]:checked').val() === 'new' ) {
            $ccForm.slideDown( 200 );
        } else {
            $ccForm.slideUp( 200 );
        }
    });

    function stripeFormHandler () {
        if ( $('#payment_method_wc_stripe').is(':checked') && ( ! $('input[name="wc_stripe_card"]').length || $('input[name="wc_stripe_card"]:checked').val() === 'new' ) ) {

            if ( ! $( 'input.stripe_token' ).length ) {
                var billingName = ($('#wc_stripe-billing-name').length) ? $('#wc_stripe-billing-name').val() : $('#billing_first_name').val() + ' ' + $('#billing_last_name').val(),
                    billingZip = ($('#wc_stripe-billing-zip').length) ? $('#wc_stripe-billing-zip').val() : $('#billing_postcode').val(),
                    cardExpiry = $('.wc_stripe-card-expiry').payment('cardExpiryVal');

                var stripeData = {
                    number          : $('.wc_stripe-card-number').val(),
                    cvc             : $('.wc_stripe-card-cvc').val(),
                    exp_month       : cardExpiry.month,
                    exp_year        : cardExpiry.year,
                    name            : billingName,
                    address_line1   : $('#billing_address_1').val(),
                    address_line2   : $('#billing_address_2').val(),
                    address_state   : $('#billing_state').val(),
                    address_zip     : billingZip,
                    address_country : $('#billing_country').val()
                };

                console.log(stripeData);

                $form.parent().block({
                    message: null,
                    overlayCSS: {
                        background: '#fff url(' + woocommerce_params.ajax_loader_url + ') no-repeat center',
                        opacity: 0.6
                    }
                });

                // Send data to stripe
                Stripe.createToken( stripeData, stripeResponseHandler );

                // Prevent form from submitting
                return false;
            }
        }

        return true;
    }

    function stripeResponseHandler ( status, response ) {

        if ( response.error ) {
            // show the errors on the form
            $('.payment-errors, .stripe_token').remove();
            $ccForm.before( '<span class="payment-errors required">' + response.error.message + '</span>' );
            $form.parent().unblock();

        } else {
            // insert the token into the form so it gets submitted to the server
            $form.append( '<input type="hidden" class="stripe_token" name="stripe_token" value="' + response.id + '"/>' );
            $form.submit();
        }
    }
});

jQuery( function( $ ) {
    $( '.wc_stripe-card-number' ).payment( 'formatCardNumber' );
    $( '.wc_stripe-card-expiry' ).payment( 'formatCardExpiry' );
    $( '.wc_stripe-card-cvc' ).payment( 'formatCardCVC' );

    $( 'body' )
        .on( 'updated_checkout', function() {
            $( '.wc_stripe-card-number' ).payment( 'formatCardNumber' );
            $( '.wc_stripe-card-expiry' ).payment( 'formatCardExpiry' );
            $( '.wc_stripe-card-cvc' ).payment( 'formatCardCVC' );
        });
} );