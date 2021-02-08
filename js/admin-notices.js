/**
 * Better Font Awesome admin notice JS
 *
 * @since 1.7.3
 */
( function( $ ) {

    'use strict';

    $( function() {

        $( '#better-font-awesome-testing-notice' ).on( 'click', '.notice-dismiss', function() {

            var data = {
                'action': 'bfa_dismiss_testing_admin_notice'
            };

            $.post(
                ajaxurl,
                data,
                function (response) {}
            );
        });
    });
})( jQuery );
