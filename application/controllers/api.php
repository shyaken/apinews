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

	public function index()
	{
		die("Access denied");
	}

	public function getList()
	{
		if(isset($_REQUEST['page'])) {
			$param['page'] = $_REQUEST['page'];
		} else {
			$param['page'] = 1;
		}
		if (isset($_REQUEST['access_key'])) $param['access_key'] = $_REQUEST['access_key'];
			else $param['access_key'] = "";
		if (isset($_REQUEST['t'])) $param['time'] = $_REQUEST['t'];
			else $param['time'] = time();
		$access_key = md5($param['page'].$param['time'].SALT);
		if ($param['access_key'] !== $access_key ) {
			//die ("Wrong access_key");
		}
		$response = array ();
		$response['category_list'] = $this->category;
		foreach ($this->category as $key => $value) {
			$response[$key] = $this->getCategoryDetail($key,$param['page']);
		}
		die(json_encode($response));
	}

	private function getCategoryDetail($category_id,$page) {
		$url = $this->homepage_url."category/".$category_id."/";
		if (intval($page) > 1) {
			$url = $url . 'page/'.$page;
		}
		$content = file_get_contents($url);
		$content = preg_replace('/\s+/m', " ", $content);
		$header_pattern = '/<h2 class="PostHeaderIcon-wrapper" style="margin:0;padding:0;"><a href="(.*?)".*?>(.*?)<\/a><\/h2>/';
		$content_pattern = '/<div class="cContent"><p>(.*?)<a/m';
		$headers = array();
		$contents = array();
		preg_match_all($header_pattern, $content, $headers);
		preg_match_all($content_pattern, $content, $contents);
		$result = array();
		foreach($headers[2] as $key => $value) {
			$result[$key]['header'] = $value;
			$result[$key]['content'] = $contents[1][$key];
			$result[$key]['url'] = $headers[1][$key];
		}
		return $result;
	}

	public function getDetail() 
	{
		if(isset($_REQUEST['url'])) {
			$param['url'] = $_REQUEST['url'];
		} else {
			die("wrong api call");
		}
		$param['access_key'] = "";
		if (isset($_REQUEST['access_key'])) $param['access_key'] = $_REQUEST['access_key'];
			//else die("wrong api call");
		$access_key = md5($param['url'].SALT);
		if ($param['access_key'] !== $access_key ) {
			//die ("Wrong access_key");
		}
		$response = array ('test','hi');
		$content = file_get_contents($param['url']);
		$content = preg_replace('/\s+/', ' ', $content);
		$header_pattern = '/<h2 class="art-PostHeader"><a.*?>(.*?)<\/a><\/h2>/';
		$content_pattern = '/<div class="art-PostContent">(.*?)<\/div> <div class="cleared">/';
		preg_match_all($header_pattern, $content, $headers);
		preg_match_all($content_pattern, $content, $contents);
		$raw_content = $content[1][0];
		$raw_content = str_replace('</p>', "\n", $raw_content);
		$raw_content = str_replace('<p>', "", $raw_content);
		$response['header'] = $header[1][0];
		$response['html_content'] = $content[1][0];
		$response['raw_content'] = $raw_content;
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