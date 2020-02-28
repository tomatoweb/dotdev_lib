<a href="<?php echo site_url('/posts/create'); ?>" class="btn btn-success float-right">Create a new Post</a>

<h2><?php echo $title; ?></h2>

<?php foreach($posts as $post) : ?>

<div class="my-3 p-3 border border-secondary">

	<h4><?php echo $post['title']; ?></h4>

	<small class="text-muted">Posted on: <?php echo $post['created_at']; ?></small>

	<p><?php echo $post['body']; ?></p>

	<a href="<?php echo site_url('/posts/'.$post['slug']); ?>" class="btn btn-info">read more</a>

</div>

<?php endforeach; ?>	

