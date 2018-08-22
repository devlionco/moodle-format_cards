define(['jquery'], function($) {
'use strict';

    return {
        init: function() {

            // open popup
            // $('.format-cards .section.img-text').parent().parent().click(function() {
            $('.format-cards .section .summary').click(function() {
                $('body.format-cards').css('overflow', 'hidden');
                $(this).parent().children('.wrapper').addClass('wrapper-open');
            });

            const block = document.querySelector('.sections');
            block.addEventListener('click', function(event){

              let target = event.target;
              while (!target.classList.contains('sections')) {
                if (target.nodeName === 'A' && !target.classList.contains('quickeditlink') && !target.classList.contains('change_image')) {
                  event.preventDefault();
                  $('body.format-cards').css('overflow', 'hidden');
                  $(target).parents('.content').children('.wrapper').addClass('wrapper-open');
                  return;
                }
                target = target.parenNode;
              }

            });

            // close popup
            $('.format-cards .activities.img-text .activity-header .header-btn-close').click(function(event) {
                event.stopPropagation();
                $('body.format-cards').css('overflow', 'auto');
                $(this).closest('.wrapper').removeClass('wrapper-open');
            });

            // close by esc
            $(document).keydown(function(e) {
              if (e.keyCode == 27) {
                $('body.format-cards').css('overflow', 'auto');
                $('.wrapper').removeClass('wrapper-open');
              }
            });

        }
    };
});
