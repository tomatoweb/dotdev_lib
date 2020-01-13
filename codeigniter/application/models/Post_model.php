<?php

/*
	add to config/config.php
	$autoload['model'] = array('post_model');
*/

class Post_model extends CI_Model{

	public function __construct(){

		$this->load->database();
	}

	public function get_posts($slug = FALSE){

		if($slug === FALSE){

			$this->db->order_by('id', 'DESC');

			$query = $this->db->get('post');
			return $query->result_array();
		}

		$query = $this->db->get_where('post', array('slug' => $slug));

		return $query->row_array(); 
	}

	public function create_post(){

		$data = array(

			'title' => $this->input->post('title'),
			'slug'  => url_title($this->input->post('title')),
			'body'  => $this->input->post('body')
		);

		return $this->db->insert('post', $data);
	}


	public function delete_post($id){

		$this->db->where('id', $id);
		$this->db->delete('post');

		return true;
	}
}