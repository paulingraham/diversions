<!DOCTYPE html>
<html lang="en">
<head>

<!-- {$title}
{$tags_hashed} -->

<meta charset="utf-8">

<meta name="viewport" content="width=device-width">

<link rel="shortcut icon" href="favicon.ico" />

<title>{$title}</title>

<link rel="canonical"						href="https://{$domain}/{$title_smpl}.html">

<meta name='title' 						content='{$title}'>

<meta name='og:url' 						content='http://{$domain}/{$title_smpl}.html'>

<meta name='twitter:url' 				content='http://{$domain}/{$title_smpl}.html'>

<meta property='og:type' 				content='article'>

<meta name='description' 				content='{$description}'>

<meta property='og:description' 	content='{$description}'>

<!-- default page image -->
<meta property='og:image' 			content='http://{$domain}/{$site_img}'>

<meta property='og:site_name' 		content='{$sitename}'>

<style>

<?php include('css-pubsys.css') ?>

<?php include('css-diversions.css') ?>

<?php include('bigfoot-js/styles/bigfoot-default.css') ?>

</style>

<script>$.bigfoot();</script>


</head><body><!-- <##> -->

<nav><strong><a href="index.html">Diversions</a></strong> <img src="imgs/diversions-logo-30px.jpg" height="15" width="15" style="vertical-align:-2px"> Fitness, family, fiction</nav>

<article><!--  article start -->

<header>

<h1 id='title'>{$title} <span id="subtitle">{$subtitle}</span></h1>

<div class="metadata">
<address class="author"><a rel="author" href="index.html#about" title="Susan Ingraham">Susan Ingraham</a></address> &nbsp; <time pubdate datetime="{$date1}" title="{$date2}">{$date2}</time> &nbsp; {$tags}</div>

</header>

{$content}

</article>

<footer class="bio"><img data-src="imgs/susan-75x87-web.jpg" class="lazyimg" width="75" height="87" class='lazyimg'>I taught in the public schools for 32 years. After retirement, I trained to become a fitness instructor for older adults. I enjoy working on family history, writing novels, and researching the latest fitness news for my class participants.</footer>

<nav class="footer_nav">
<a href="https://susaningraham.net">Susan&thinsp;Ingraham<span class='color_light_gray'>&#8202;.&#8202;net</span></a>
</nav>

<script type='text/javascript' src='https://code.jquery.com/jquery-3.4.1.min.js' ></script>

<script type="text/javascript">

<?php include('bigfoot-js/scripts/bigfoot.min.js') ?>

$.bigfoot();

<?php include('lazyload-imgs.js') ?>

</script>


</body></html>