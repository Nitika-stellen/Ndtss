(function($) {
    "use strict";
    $( document ).ready( function () { 
        $("body").on("click",".notice.is-dismissible",function(e){
            var data = {
                'action': 'yeekit_dismiss_noty',
                'id': $(this).data('id'),
                'nonce': yeekit_list_addons.nonce,
            };
            $.post(ajaxurl, data, function() {   
            });
        })
    })
})(jQuery);