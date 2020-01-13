<?php

class Posts extends CI_Controller
{
	
	public function index()
	{

		$data['title'] = 'Latest Posts';

		$data['posts'] = $this->post_model->get_posts();		

		$this->load->view('templates/header', $data);
		$this->load->view('posts/index');
		$this->load->view('templates/footer');
	}

	public function view($slug = NULL){

		if($slug === NULL){
			show_404();
		}

		$data['post'] = $this->post_model->get_posts($slug);

		$this->load->view('templates/header', $data);
		$this->load->view('posts/view');
		$this->load->view('templates/footer');
	}

	/*
		add to config/autoload.php
		$autoload['helper'] = array('url', 'form');		
		$autoload['libraries'] = array('form_validation');
	*/

	public function create(){

		$data['title'] = 'Create a new Post';

		$this->form_validation->set_rules('title', 'Title', 'required', array('required'=>'Il manque le titre.'));
		$this->form_validation->set_rules('body', 'Body', 'required');

		if($this->form_validation->run() === FALSE){
			$this->load->view('templates/header');
			$this->load->view('posts/create', $data);
			$this->load->view('templates/footer');
		} else {
			$this->post_model->create_post();

			redirect('posts');
		}
		

		
	}
}