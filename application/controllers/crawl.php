<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Crawl extends CI_Controller{

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */

	private $homepage_url = "http://www.tremeritus.com/";
	private $category = array (
		'columnists' => 'Columnists',
		'top-story' => 'Editorial',
		'ge' => 'Elections',
		'forum' => 'Letters',
		'daily-musings' => 'Opinion',
		'revisited' => 'Revisited',
		'snippets' => 'Snippets',
		'sports' => 'Sports',
		'zassorted' => 'Z-Assorted',
		'chinese-section' => 'Chinese'
		);
	private $specific_category = array (
		'feature_article' => 'Featured Articles',
		'sticky_recent_article' => 'Sticky & Recent Articles'
		);

	private $log_file_path = '/var/www/trrapi/logs/';

	public function crawlCat($cat_id,$page)
	{
		echo "Start crawling content for $cat_id at page $page\n";
		$result = $this->getCategoryDetail($cat_id,$page);
		$this->updateDb($result);
		echo "Finish crawling data for $cat_id page $page\n";
	}

	function getSpecificCat ($cat_id,$page,$content = null) {
		$url = $this->homepage_url;
		if ($page >= 2) {
			$url .= "page/$page/";
		}
		if($content === null) $content = file_get_contents($url);
		$content = preg_replace('/\s+/m', ' ', $content);
		if ($cat_id === 'sticky_recent_article') {
			$sticky_content = strstr($content, 'Sticky & Recent Articles');
			return $this->getCategoryDetail($cat_id,$page,$sticky_content);
		}
		else {
			//echo $content;
			$post_pattern = '/<div class="board_item"> <p><a href="(.*?)"><img src="(.*?)".*?>.*?<a.*?>(.*?)<\/a><\/strong>(.*?)<\/p>/';
			preg_match_all($post_pattern, $content, $posts);
			$result = array();
			foreach ($posts[0] as $key => $value) {
				$result[$key]['title'] = $posts[3][$key];
				//$result[$key]['description'] = $posts[4][$key];
				$result[$key]['url'] = str_replace($this->homepage_url, '', $posts[1][$key]);
				$p_id = $result[$key]['url'];
				$result[$key]['post_id'] = md5($p_id.$cat_id);
				$result[$key]['cat_id'] = $cat_id;
				if($this->checkExist($result[$key]['post_id'])) {
					continue;
				}
				$detail = $this->getDetail($p_id);
				$result[$key]['img'] = $posts[2][$key];
				if($detail != null) {
					$result[$key] = array_merge($result[$key],$detail);
				}
			}
			return $result;
		}
	}

	private function getCategoryDetail($category_id,$page,$content = null) {
		if (isset($this->specific_category[$category_id]) && $content === null) {
			return $this->getSpecificCat($category_id,$page);
		}
		$url = $this->homepage_url."/page/$page";
		if($content === null) {
			$url = $this->homepage_url."category/".$category_id."/";
			if (intval($page) > 1) {
				$url = $url . 'page/'.$page.'/';
			}
			$content = file_get_contents($url);
			$content = preg_replace('/\s+/m', " ", $content);
		}
		
		write_file($this->log_file_path.'logurl.data', $url."\n",'a+');
		$header_pattern = '/<h2 class="PostHeaderIcon-wrapper" style="margin:0;padding:0;"><a href="(.*?)".*?>(.*?)<\/a><\/h2>/';
		//$content_pattern = '/<div class="cContent"><p>(.*?)<a/m';
		$img_pattern = '/<div class="PostContent"> <a href=".*?" rel="bookmark" title=".*?"><img class="alignleft" src="(.*?)" .*?><\/a>/';
		$headers = array();
		$contents = array();
		$imgs = array();
		preg_match_all($header_pattern, $content, $headers);
		//preg_match_all($content_pattern, $content, $contents);
		preg_match_all($img_pattern, $content, $imgs);
		$result = array();
		foreach($headers[2] as $key => $value) {
			$result[$key]['title'] = $value;
			//$result[$key]['description'] = $contents[1][$key];
			$result[$key]['url'] = str_replace($this->homepage_url, '', $headers[1][$key]);
			$result[$key]['cat_id'] = $category_id;
			$p_id = $result[$key]['url'];
			$result[$key]['post_id'] = md5($p_id.$category_id);
			$result[$key]['img'] = $imgs[1][$key];
			if($this->checkExist($result[$key]['post_id'])) {
				continue;
			}
			$detail = $this->getDetail($p_id);
			if($detail != null) {
				$result[$key] = array_merge($result[$key],$detail);
			} else {
				unset($result[$key]);
			}
		}
		return $result;
	}

	public function updateDb($params) {
		foreach($params as $record) {
			if($this->checkExist($record['post_id'])) {
				echo "existed record, continue";
				continue;
			}
			$record['crawl_time'] = time();
			$this->db->insert('records',$record);
		}
	}

	public function getDetail($id) 
	{
		
		$param['url'] = $this->homepage_url.$id;
		
		$content = file_get_contents($param['url']);
		$content = preg_replace('/\s+/', ' ', $content);
		$headers = array();
		$contents = array();
		$date = array();
		$header_pattern = '/<h2 class="art-PostHeader"><a.*?>(.*?)<\/a><\/h2>/';
		$content_pattern = '/<div class="art-PostContent">(.*?)<\/div> <div class="cleared">/';
		$date_pattern = '/<img src=".*?PostDateIcon\.png.*?".*?>(.*?)<img/';
		preg_match_all($header_pattern, $content, $headers);
		preg_match_all($content_pattern, $content, $contents);
		preg_match_all($date_pattern, $content, $date);
		if(isset($date[1][0]) && isset($contents[1][0]) ) {
			$raw_content = $contents[1][0];
			$raw_content = preg_replace('/<[^>]*>/', " ", $raw_content);
			$response['html_content'] = $contents[1][0];
			$response['raw_content'] = $raw_content;
			$response['date'] = strtotime(trim(str_replace('|', '', $date[1][0])));
			return $response;
		} else {
			return null;
		}
	}

	private function checkExist($post_id)
	{
		$query = $this->db->get_where('records',array('post_id' => $post_id));	
		if($query->num_rows() > 0) {
			return true;
		}
		return false;
	}

	public function main() {
		echo "<pre>";
		if (isset($_GET['all']) && $_GET['all'] == 1) {
			unset($this->specific_category['feature_article']);
			write_file($this->log_file_path.'logurl.data', "start crawl all data at ".date('Y-m-d H:i:s')."\n",'w+');
			if(!isset($_GET['start_page'])) {
				$current_page = 0;
			} else {
				$current_page = $_GET['start_page'];
			}
			while ($current_page < 5000) {
				$current_page++;
				echo "Crawling data for page $current_page\n";
				write_file($this->log_file_path.'logurl.data', "Crawling data for page $current_page\n",'a+');
				foreach (array_merge($this->category, $this->specific_category) as $key => $value) {
					$this->crawlCat($key,$current_page);
				}
				write_file($this->log_file_path.'logurl.data', "End $current_page\n",'a+');
			}
		} else {
			write_file($this->log_file_path.'logurl.data', "start crawl new data at ".date('Y-m-d H:i:s')."\n",'a+');
			foreach (array_merge($this->category, $this->specific_category) as $key => $value) {
				$this->crawlCat($key,1);
				$this->crawlCat($key,2);
				$this->crawlCat($key,3);
			}
		}
		echo "</pre>";
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
