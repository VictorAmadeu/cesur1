$(document).ready(function() {
  /* Change company selected */
  $('#company_select_global_id').change(() => { 
      $('#loadingModal').modal('show');
      $.ajax({
          type:"GET", 
          url:"/prt/changeSC/"+$('#company_select_global_id').val(), 
          cache: false,
          contentType: false,
          processData: false,
          success: function (data) {
            setTimeout(function() {location.reload();}, 500);
          },
      });
  });

  /* Change company selected */
  $('#user_select_global_user').change(() => { 
    $('#loadingModal').modal('show');
    $.ajax({
        type:"GET", 
        url:"/prt/changeUR/"+$('#user_select_global_user').val(), 
        cache: false,
        contentType: false,
        processData: false,
        success: function (data) {
          setTimeout(function() {location.reload();}, 500);
        },
    });
  });  
});