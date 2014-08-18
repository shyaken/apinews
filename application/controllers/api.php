<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Api extends CI_Controller {

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
		'zassorted' => 'Z-Assorted'
		);
	private $specific_category = array (
		'feature_article' => 'Featured Articles',
		'sticky_recent_article' => 'Sticky & Recent Articles'
		);

	public function index()
	{
		die("Access denied");
	}

	public function getList()
	{
		$validate = "";
		if(isset($_REQUEST['page'])) {
			$param['page'] = $_REQUEST['page'];
			$validate .= $param['page'];
		} else {
			$param['page'] = 1;
		}

		if(isset($_REQUEST['cat_id'])) {
			$param['cat_id'] = $_REQUEST['cat_id'];
			$validate .= $param['cat_id'];
		}

		if (isset($_REQUEST['access_key'])) {
			$param['access_key'] = $_REQUEST['access_key'];
		} else {
			$param['access_key'] = "";
		}

		if (isset($_REQUEST['t'])) {
			$param['time'] = $_REQUEST['t'];
			$validate .= $param['time'];
			$timediff = abs($param['time'] - time());
			if ($timediff > 24*60*60) {
				$response['status'] = false;
				$response['message'] = "access_key has been expired";
				echo json_encode($response);
				die;
			}
		} else { 
			$param['time'] = time();
			$validate .= $param['time'];
		}
		$response = array ();
		$response['status'] = true;
		$validate .= SALT;
		$access_key = md5($validate);
		if ($param['access_key'] !== $access_key && !isset($_REQUEST['ignore_access'])) {
			$response['status'] = false;
			$response['message'] = "Wrong access_key";
			echo json_encode($response);
			die;
		}
		//$response['category_list'] = array_merge($this->category,$this->specific_category);
		if(isset($param['cat_id'])) {
			$response[$param['cat_id']]	 = $this->getCategoryDetail($param['cat_id'],$param['page']);
		} else {
			foreach (array_merge($this->category, $this->specific_category) as $key => $value) {
				$response[$key] = $this->getCategoryDetail($key,$param['page']);
			}
		}
		if(!isset($_REQUEST['format']) || $_REQUEST['format'] !== 'ios') {
			die(json_encode($response));	
		} else {
			$ios_resposne = array ();
			foreach ($response as $key => $value) {
				$ios_resposne[] = array ('category' => $key, 'data' => $value);
			}
			die(json_encode($response));
		}
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
				$result[$key]['id'] = str_replace($this->homepage_url, '', $posts[1][$key]);
				$result[$key]['img'] = $posts[2][$key];
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
				$url = $url . 'page/'.$page;
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
			$result[$key]['id'] = str_replace($this->homepage_url, '', $headers[1][$key]);
			$result[$key]['img'] = $imgs[1][$key];
		}
		return $result;
	}

	public function getDetail() 
	{
		$param['id'] = "";
		$param['url'] = "";
		$param['t'] = "";
		$response = array();
		$response['status'] = true;
		if(isset($_REQUEST['id'])) {
			$param['id'] = $_REQUEST['id'];
			$param['url'] = $this->homepage_url.$_REQUEST['id'];
		} else {
			$response['status'] = false;
			$response['message'] = "id is required in this api";
		}
		$param['access_key'] = "";
		if (isset($_REQUEST['access_key'])) $param['access_key'] = $_REQUEST['access_key'];
			else {
				$response['status'] = false;
				$response['message'] = "access_key is required in this api";
			}
		if (isset($_REQUEST['t'])) {
			$param['t'] = $_REQUEST['t'];
			$timediff = abs($param['t'] - time());
			if ($timediff > 24*60*60) {
				$response['status'] = false;
				$response['message'] = "access_key has been expired";
				echo json_encode($response);
				die;
			}
		} else {
				$response['status'] = false;
				$response['message'] = "time is required in this api";
			}
		$validate = $param['id'].$param['t'].SALT;
		$access_key = md5($validate);
		if ($param['access_key'] !== $access_key && !isset($_REQUEST['ignore_access'])) {
			$response['status'] = false;
			$response['message'] = "access_key is invalid";
		}
		if($response['status'] === false) {
			die(json_encode($response));
		}
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
		$response['header'] = $headers[1][0];
		//$response['html_content'] = $contents[1][0];
		$response['content'] = $raw_content;
		$response['date'] = trim(str_replace('|', '', $date[1][0]));
		die(json_encode($response));
	}

	public function test() {
		$list = file_get_contents("http://apinews.local/api/getList");
		$result = json_decode($list,true);
		//print_r($result);
		$test_url = $result['columnists'][0]['url'];
		$detail = file_get_contents("http://apinews.local/api/getDetail?url=".$test_url);
		echo $detail;
		die;
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */