$(document).ready(function(){

    $('.js-like-article').on('click', function(){

        e.preventDefault();

        var $link = $(e.currentTarget);

        $link.toggleClass('fa-heart-o').toggleClass('fa-heart');

    });

    console.log("app.js");


});
