<?php

class Pages extends CI_Controller{

	public function view($page = 'home'){		

		/*
			add to config/routes.php
			$route['default_controller'] = 'pages/view'; // short alias
			$route['(:any)'] = 'pages/view/$1';			 // any static page (/home, /about, /)

			also add to config/autoload.php
			$autoload['helper'] = array('url'); // required for base_url() entre autres

		*/


		if (!file_exists(APPPATH.'views/pages/'.$page.'.php')) {
			
			show_404();
		}

		$data['title'] = ucfirst($page);

		$this->load->view('templates/header', $data);
		$this->load->view('pages/'.$page);
		$this->load->view('templates/footer');
	}
}