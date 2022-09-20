jQuery(document).ready(function($) {

    // Init
    wooCheckout();

    function wooCheckout() {
        
        $( '.woocommerce-checkout #saveinfo' ).parent().hide();

        $( document.body ).on( 'updated_checkout', function() {
    
            $( '.woocommerce-checkout #saveinfo' ).parent().hide();
    
            if ( $('body.logged-in').length > 0 ) {
    
                $( '.woocommerce-checkout #saveinfo' ).prop( 'checked', true );
                
            }
    
        });

    }



}, jQuery);