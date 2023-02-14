( ( $ ) => {
	$( () => {
		$( document.body ).on( "payment_method_selected", ( event ) => {
			if( $( '#payment_method_payson_checkout' ).is( ":checked" ) ) {
				$( "#payment" ).addClass( "payson_checkout_selected" );
				$.ajax( {
					url: window.ajaxurl + "?action=payson_checkout",
					method: "POST",
					dataType: "JSON",
					success: response => {
						$( ".payment_box.payment_method_payson_checkout" ).html( response.data.snippet );
					}
				} );
			}
			else {
				$( "#payment" ).removeClass( "payson_checkout_selected" );
			}
		} );
	} );
} )( jQuery );
