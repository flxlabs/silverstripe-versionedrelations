(function($) {
    $( document ).ajaxComplete(function( event, xhr, settings ) {
        if (xhr.responseText) {
            $response = $(xhr.responseText);
            var $divElem = $response.filter("#reorder-happened");
            $('body').append($divElem);

            if ($divElem.length){
                $('.cms-edit-form .Actions #Form_EditForm_action_publish').button({
                    showingAlternate: true
                });
                $("#record-" + $divElem.data("object-id")).addClass("status-modified");
                $( document ).remove("#reorder-happened");
            }
        }
    });
})(jQuery);