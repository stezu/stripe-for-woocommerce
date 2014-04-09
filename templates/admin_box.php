    <script>
    jQuery(function($){
        $('body').on('click', '#capture_striper',  function(){
            var amount =  $('[name=capture_amount]').val();
            $.post('/wp-admin/admin-ajax.php', {
                action:  'capture_striper', 
                amount:   $('[name=capture_amount]').val() * 100, 
                order_id: $('#post_ID').val()
            }, function(r){
                $('.capture_div').html('<h3>Captured Order#'+$('#post_ID').val()+' for '+amount+'</h3>');                
            });
            return false;
        });
    });
</script>
<div class="capture_div">

<? 
    print sprintf("<label>Amount </label> <input name='capture_amount' value='%s' /><button id='capture_striper' class='button' href='#' >Capture</button>&nbsp; <strong>Must be the same or less than original amount</strong>",esc_attr( $data['_order_total'][0] ));
?>

</div>



