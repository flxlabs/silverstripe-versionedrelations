(function($) {
    $( document ).ajaxComplete(function( event, xhr, settings ) {
        if (xhr.responseText && typeof(xhr.responseText) === "string") {
            if(xhr.responseText.indexOf("reorder-happened") !== -1) {
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
        }
    });
})(jQuery);