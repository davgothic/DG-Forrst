<?php
/*
Plugin Name: DG-Forrst
Plugin URI: http://davgothic.com/dg-forrst-wordpress-plugin
Description: A widget that displays your Forrst posts
Author: David Hancock
Version: v1.0
Author URI: http://davgothic.com
*/

class DG_Forrst extends WP_Widget {

	private $dg_defaults;
	private $forrst_cache;

	/**
	 * Let's get things rolling!
	 */
	public static function dg_load()
	{
		add_action('widgets_init', array(__CLASS__, 'dg_widgets_init'));
	}

	/**
	 * Init the widget
	 */
	public static function dg_widgets_init() {
		register_widget('DG_Forrst');
	}

	/**
	 * Construct a new instance of the widget
	 */
	public function __construct()
	{
		$widget_options = array(
			'classname'    => 'DG_Forrst',
			'description'  => __('Displays your latest posts on Forrst.')
		);

		parent::__construct('DG_Forrst', __('DG-Forrst'), $widget_options);

		$this->forrst_cache = dirname(__FILE__).DIRECTORY_SEPARATOR.'dg-forrst.cache';

		// Set up the widget defaults
		$this->dg_defaults = array(
			'title'        => __('Latest Forrst Posts'),
			'username'     => 'davgothic',
			'postcount'    => '5',
			'cachelength'  => '300',
			'followtext'   => __('Follow me on forrst'),
		);
	}

	/**
	 * Display the widget
	 */
	public function widget($args, $instance)
	{
		extract($args, EXTR_SKIP);

		$title        = apply_filters('widget_title', empty($instance['title']) ? $this->dg_defaults['title'] : $instance['title']);
		$username     = $instance['username'];
		$postcount    = $instance['postcount'];
		$cachelength  = $instance['cachelength'];
		$followtext   = $instance['followtext'];
		$feed         = $this->dg_get_posts($username, $postcount, $cachelength);

		if ($feed === FALSE)
			return;

		echo $before_widget;

		if ($title)
		{
			echo $before_title, $title, $after_title;
		}

		?>

			<ul>
				<?php echo $feed ?>
			</ul>
			<?php if ($followtext): ?>
				<a href="http://forrst.com/people/<?php echo $username ?>" class="dg-forrst-follow-link"><?php echo $followtext ?></a>
			<?php endif ?>

		<?php

		echo $after_widget;
	}

	/**
	 * Handle widget settings update
	 */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;

		$instance['title']        = strip_tags($new_instance['title']);
		$instance['username']     = strip_tags($new_instance['username']);
		$instance['postcount']    = (int) $new_instance['postcount'];
		$instance['cachelength']  = (int) $new_instance['cachelength'];
		$instance['followtext']   = strip_tags($new_instance['followtext']);

		if ( ! $postcount = $instance['postcount'])
		{
 			$instance['postcount'] = $this->dg_defaults['postcount'];
		}
 		else if ($postcount < 1)
		{
 			$instance['postcount'] = 1;
		}
		else if ($postcount > 25)
		{
 			$instance['postcount'] = 25;
		}

		// Invalidate the cache
		if (file_exists($this->forrst_cache))
		{
			unlink($this->forrst_cache);
		}

		return $instance;
	}

	/**
	 * The widget settings form
	 */
	public function form($instance)
	{
		$instance = wp_parse_args( (array) $instance, $this->dg_defaults);

		?>
		<p>
			<label for="<?php echo $this->get_field_id('title') ?>"><?php _e('Title:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title') ?>" name="<?php echo $this->get_field_name('title') ?>" type="text" value="<?php echo $instance['title'] ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('username') ?>"><?php _e('Forrst Username:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('username') ?>" name="<?php echo $this->get_field_name('username') ?>" type="text" value="<?php echo $instance['username'] ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('postcount') ?>"><?php _e('Number of posts (max 25):') ?></label>
			<input id="<?php echo $this->get_field_id('postcount') ?>" name="<?php echo $this->get_field_name('postcount') ?>" type="text" value="<?php echo $instance['postcount'] ?>" size="3" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('cachelength') ?>"><?php _e('Length of time to cache posts (seconds):') ?></label>
			<input id="<?php echo $this->get_field_id('cachelength') ?>" name="<?php echo $this->get_field_name('cachelength') ?>" type="text" value="<?php echo $instance['cachelength'] ?>" size="5" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('followtext') ?>"><?php _e('Follow Link Text:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('followtext') ?>" name="<?php echo $this->get_field_name('followtext') ?>" type="text" value="<?php echo $instance['followtext'] ?>" />
		</p>
		<?php
	}

	/**
	 * Fetch the posts from Forrst or cache
	 */
	private function dg_get_posts($username, $postcount, $cachelength)
	{
		$forrst_api = 'http://forrst.com/api/v2/user/posts?username='.$username.'&limit='.$postcount;

		// If the cache file has expired, fetch posts
		if ( ! file_exists($this->forrst_cache) OR filemtime($this->forrst_cache) < (time() - $cachelength))
		{
			$curl = curl_init($forrst_api);
			curl_setopt($curl, CURLOPT_USERAGENT, 'DG-Forrst/1.0 (+http://github.com/davgothic/DG-Forrst)');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			$result = curl_exec($curl);
			curl_close ($curl);

			if ( ! $result)
			{
				// There was a problem, abort!
				return FALSE;
			}
			else
			{
				// Cache the results
				file_put_contents($this->forrst_cache, $result);
			}
		}
		else
		{
			// Read the posts from cache
			$result = file_get_contents($this->forrst_cache);
		}

		$posts = json_decode($result);

		$html = '';
		foreach ($posts->resp->posts as $post)
		{
			$html .= '<li>';
			$html .= '<span>'.$post->title.'</span> ';
			$html .= '<a href="'.$post->post_url.'" class="dg-forrst-created-at">'.$this->dg_relative_time($post->created_at).'</a>';
			$html .= '</li>';
		}

		return $html;
	}

	/**
	 * Display a user friendly string for the post time
	 */
	private function dg_relative_time($time)
	{
		$parsed_date = strtotime($time);
		$relative_to = time();
		$delta       = (int) ($relative_to - $parsed_date);

		if ($delta < 60)
		{
			return 'less than a minute ago';
		}
		else if ($delta < 120)
		{
			return 'about a minute ago';
		}
		else if ($delta < (60 * 60))
		{
			return (int) ($delta / 60).' minutes ago';
		}
		else if ($delta < (120 * 60))
		{
			return 'about an hour ago';
		}
		else if ($delta < (24 * 60 * 60))
		{
			return 'about '. (int) ($delta / 3600).' hours ago';
		}
		else
		{
			return date('j F', $parsed_date);
		}
	}

}

DG_Forrst::dg_load();

?>