jQuery(document).ready(function ($) {

  //Admin Settings
   $("#pcafe_tab_box div.tab_item").hide();
   $("#pcafe_tab_box div:first").show();
   $(".pcafe_menu_wrap li:first").addClass("active");

   // Change tab class and display content
   $(".pcafe_menu_wrap a").on("click", function (event) {

    if ($(this).hasClass("demo_btn")) {
        // Open in a new tab
        window.open($(this).attr("href"), "_blank");
    } else {
        // Prevent default for other links
        event.preventDefault();
        $(".pcafe_menu_wrap li").removeClass("active");
       $(this).parent().addClass("active");
       $("#pcafe_tab_box div.tab_item").hide();
       $($(this).attr("href")).show();
    }
   });

   $('.p__install').on('click', function (e) {
       $(this).find('.p_btn_text').text('Installing...');
       $(this).find('.loader').addClass('active');
   });

   $('.p__activate').on('click', function (e) { 
       $(this).find('.p_btn_text').text('Activating...');
       $(this).find('.loader').addClass('active');
   });

});