<?php
/*
  Plugin Name: ツイートランキング
  Version: 0.1-alpha
  Description: Twitterのツイートアクティビティデータをインポートして人気ツイートランキングを表示します。
  Author: kurozumi
  Author URI: http://a-zumi.net
  Plugin URI: http://a-zumi.net
  Text Domain: tweet-activity
  Domain Path: /languages
 */

add_action('widgets_init', function () {
	register_widget("TweetActivityRanking_Widget");
});

class TweetActivityRanking_Widget extends WP_Widget {

	public function __construct()
	{
		parent::__construct(
			"tweet-activity-ranking", "ツイートランキング", array(
				'description' => "Twitterのツイートアクティビティデータをインポートして人気ツイートランキングを表示します。"
			)
		);

		add_action('admin_print_scripts-widgets.php', array($this, 'admin_scripts'));
	}

	public function widget($args, $instance)
	{
		if(!($data = $this->fgetcsv($instance)))
			return;

		$engagement = array();
		foreach ($data as $v)
			$engagement[] = $v[$instance['sort']];

		array_multisort($engagement, SORT_DESC, $data);

		printf("%s\n", $args['before_widget']);
		printf(
			'%1$s %2$s %3$s', $args['before_title'], (empty($instance['title'])) ? $args['widget_name'] : $instance['title'], $args['after_title']
		);

		$cnt = (count($data) > $instance['limit']) ? $instance['limit'] : count($data);
		
		print("<ol>\n");
		for ($i = 0; $i < $cnt; $i++) {
			printf("<li>%s</li>\n", preg_replace("/(.*)\s(https?.*)/", "$1", $data[$i]['Tweet text']));
		}
		print("</ol>\n");
		printf("%s\n", $args['after_widget']);
	}

	public function form($instance)
	{
		if (empty($instance))
			$instance = array('title' => '', 'attachement_id' => '', 'header' => 0, 'type' => '', 'error' => false,);
		
		$this->form_output($instance);
	}

	public function update($new_instance, $old_instance)
	{
		return $this->validate( $new_instance);
	}

	public function admin_scripts()
	{
		// メディアアップローダー用のスクリプトをロード
		wp_enqueue_media();

		// カスタムメディアアップローダー用のJavaScript
		wp_enqueue_script(
				'my-media-uploader', plugins_url("media-uploader.js", __FILE__), array('jquery'), filemtime(dirname(__FILE__) . '/media-uploader.js'), false
		);
	}

	public function form_output($args)
	{
		$title = isset($args['title']) ? esc_attr($args['title']) : '';
		$attachement_id = isset($args['attachement_id']) ? (int) $args['attachement_id'] : '';
		$header = isset($args['header']) ? (int) $args['header'] : 0;
		$limit = isset($args['limit']) ? esc_attr($args['limit']) : 10;
		$sort = isset($args['sort']) ? esc_attr($args['sort']) : '';
		$error = isset($args['error']) ? esc_attr($args['error']) : '';

		if (!empty($error)) {
			echo '<p><strong>' . sprintf(__('%s'), $error) . '</strong></p>';
		} elseif(!$post = get_post($attachement_id)) {
			echo '<p><strong>' . sprintf(__('%s'), "CSVファイルをアップロードして下さい。") . '</strong></p>';
		} else{
			printf('<p><strong>%sにツイートアクティビティCSVファイルが登録されました。</strong></p>', $post->post_date);
		}
		?>
		<p>
			<button id="tweet-activity-ranking" data-name="<?php echo $this->get_field_name('attachement_id'); ?>"><?php _e('ファイルをアップロード'); ?></button>
			<input name="<?php echo $this->get_field_name('attachement_id'); ?>" type="hidden" value="<?php echo $attachement_id; ?>" >
		</p>
		<p>
			<input name="<?php echo $this->get_field_name('header'); ?>" type="checkbox" value="1" <?php checked($header, 1); ?>/>
			<label for="<?php echo $this->get_field_id('header'); ?>"><?php _e('1行目をヘッダーにしますか？'); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Show up to:'); ?></label><br />
			<input class="widefat" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" style="width:50px!important" /> posts
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('sort'); ?>"><?php _e('Sort tweet by:'); ?></label>
			<select class="widefat" name="<?php echo $this->get_field_name('sort'); ?>">
				<option value="インプレッション" <?php selected( $sort, "インプレッション" );?>>インプレッション</option>
				<option value="エンゲージメント" <?php selected( $sort, "エンゲージメント" );?>>エンゲージメント</option>
				<option value="エンゲージメント率" <?php selected( $sort, "エンゲージメント率" );?>>エンゲージメント率</option>
				<option value="リツイート" <?php selected( $sort, "リツイート" );?>>リツイート</option>
				<option value="お気に入り" <?php selected( $sort, "お気に入り" );?>>お気に入り</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('タイトル:'); ?></label>
			<input class="widefat" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" >
		</p>
		<?php
	}

	public function validate($instance)
	{
		$mime_type = get_post_mime_type($instance['attachement_id']);
		if ($mime_type != 'text/csv')
			$instance['error'] = "CSVファイルをアップロードして下さい。";
		
		return $instance;
	}

	public function fgetcsv($instance)
	{
		setlocale(LC_ALL, 'ja_JP.UTF-8');
		
		$file = get_attached_file($instance['attachement_id']);
				
		if (($file === FALSE) || empty($file) || ($file = file_get_contents($file)) === FALSE) {
			return FAlSE;
		} else {
			//テンポラリファイルを作成
			$handle = tmpfile();
			// 文字コード変換
			$file = mb_convert_encoding($file, mb_internal_encoding(), mb_detect_encoding($file, 'UTF-8, EUC-JP, JIS, eucjp-win, sjis-win'));
			// バイナリセーフなファイル書き込み処理
			fwrite($handle, $file);
			// ファイルポインタの位置を先頭へ
			rewind($handle);
		}

		if (isset($instance['header'])) {
			$header = fgetcsv($handle, 0, ",");
			$header = array_map(function($header) {
				return preg_replace('/\n|\r/', '', $header);
			}, $header);
		}

		$data = array();

		while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
			if (isset($header)) {

				foreach ($header as $i => $heading_i) {
					if (isset($row[$i]))
						$line[$heading_i] = $row[$i];
				}
				$data[] = $line;
			}else {
				$data[] = $row;
			}
		}

		fclose($handle);
		
		return $data;
	}
}
