$(function () {
    $(".submitButton").on('click', function () {
        var url = $(this).attr('data-url');
        var content = $("textarea#comment_article_content");
        var idArticle = $(this).attr('id-article');
        var data = { content : content.val(),
            idArticle : idArticle
        };
        var idnewComment;
        $.ajax({
            url : url,
            type: 'POST',
            data : data
            ,
            success: function (response) {
                $('.containerErrors').empty();
                idnewComment = response.id;
            },
            error: function (response) {
                if (content.val().length === 0){
                    $('.containerErrors').empty();
                    $('.containerErrors')
                        .append('<p><span class="glyphicon glyphicon-exclamation-sign"></span>' +
                            'Votre message ne peut pas être vide.</p>');
                } else if (content.val().length === 1) {
                    $('.containerErrors').empty();
                    $('.containerErrors')
                        .append('<p><span class="glyphicon glyphicon-exclamation-sign"></span>' +
                            'Votre message doit contenir  au moins 2 caractères.</p>');
                } else if (content.val().length > 300){
                    $('.containerErrors').empty();
                    $('.containerErrors')
                        .append('<p><span class="glyphicon glyphicon-exclamation-sign"></span>' +
                            'Votre message ne peut pas contenir plus de 300 caractères.</p>');
                } else{
                    $('.containerErrors').empty();
                    $('.containerErrors')
                        .append('<p><span class="glyphicon glyphicon-exclamation-sign"></span>' +
                            'Votre message est incorrecte.</p>');
                }

            }
        }).done(function () {
            var $containerComments = $('#comments');
            var template = $containerComments.attr('comment-prototype')
                .replace(/__comment__/g, content.val())
                .replace(/__id__/g, idnewComment);
            $containerComments.append(template);
            content.val('');
        });
    });

    $("#comments").on("click", ".deleteButton", function () {
        var el = $(this).closest('.comment');
        var url = $(this).attr('data-url');
        $.ajax({
            url: url,
            method: 'DELETE'
        }).done(function () {
            el.fadeOut().remove();
        });
    });
});

