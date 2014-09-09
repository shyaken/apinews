<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class crawler {

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
			return $this->getCategoryDetail(0,0,$sticky_content);
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
				$result[$key]['post_id'] = md5($p_id);
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
		if (isset($this->specific_category[$category_id])) {
			return $this->getSpecificCat($category_id,$page);
		}
		if($content === null) {
			$url = $this->homepage_url."category/".$category_id."/";
			if (intval($page) > 1) {
				$url = $url . 'page/'.$page.'/';
			}
			$content = file_get_contents($url);
			$content = preg_replace('/\s+/m', " ", $content);
		}
		
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
			$p_id = $result[$key]['url'];
			$result[$key]['post_id'] = md5($p_id);
			$result[$key]['img'] = $imgs[1][$key];
			$detail = $this->getDetail($p_id);
			if($detail != null) {
				$result[$key] = array_merge($result[$key],$detail);
			}
		}
		return $result;
	}

	public function updateDb($params) {
		foreach($params as $record) {
			$checkExist = $this->db->get_where('records',array('post_id' => $record['post_id']));	
			if(count($checkExist) > 0) {
				continue;
			}
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
		$raw_content = $contents[1][0];
		$raw_content = preg_replace('/<[^>]*>/', " ", $raw_content);
		$response['html_content'] = $contents[1][0];
		$response['raw_content'] = $raw_content;
		if(isset($date[1][0])) {
			$response['date'] = trim(str_replace('|', '', $date[1][0]));
			return $response;
		} else {
			return null;
		}
	}

	public function main() {
		if (isset($argv[1]) && $argv[1] == 'all') {
			$current_page = 0;
			while ($current_page < 500) {
				$current_page++;
				echo "Crawling data for page $current_page\n";
				foreach (array_merge($this->category, $this->specific_category) as $key => $value) {
					$this->crawlCat($key,$current_page);
				}
			}
		} else {
			foreach (array_merge($this->category, $this->specific_category) as $key => $value) {
				$this->crawlCat($key,1);
			}
		}
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
