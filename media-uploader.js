jQuery(document).ready(function($){
    var custom_uploader;
    $(document).on('click', 'button', function(e) {
		
		var that = this;
		var name = $(this).data('name');
		
        e.preventDefault();
        if (custom_uploader) {
            custom_uploader.open();
            return;
        } 
        custom_uploader = wp.media({
            title: 'ファイルをアップロード',
            button: {
                text: '登録'
            },  
            multiple: false, // falseにすると画像を1つしか選択できなくなる
			type: 'link'
        }); 
        custom_uploader.on('select', function() {
            var images = custom_uploader.state().get('selection');
            images.each(function(file){
                $('input[name="' + name + '"]').val(file.toJSON().id);
            });
        });
        custom_uploader.open();
		
		$(that).after('<p style="color:red;">保存ボタンを押して下さい。</p>');
    }); 
});