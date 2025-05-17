<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width">

<link rel="shortcut icon" href="imgs/favicon.ico" />

<title>{$ti_tag_count} Diversions Posts Tagged '{$ti_tag}'</title>

<style>

<?php include('css-pubsys.css') ?>

<?php include('css-diversions.css') ?>

</style>

<script type='text/javascript' src='https://code.jquery.com/jquery-3.4.1.min.js' ></script>

<script type="text/javascript">

<?php include('table-sort.min.js') ?>

<?php include('table-sort-setup.js') ?>

</script>

</head><body><!-- <##> -->

<nav><strong><a href="index.html">Diversions</a></strong> <img src="imgs/diversions-logo-30px.jpg" height="15" width="15" style="vertical-align:-2px"> Fitness, family, fiction</nav>

<article><!--  article start -->

<header>

<h1 style="line-height:1.2">{$ti_tag_count} Diversion Posts Tagged <span style='color:#004080;text-transform:uppercase;
font-size:.8em;
padding: 0 4px;
border: 2px solid #BBB;
border-radius:8px;
white-space:nowrap;
'>{$ti_tag}</span></h1>

</header>

{$ti_posts_table}

</article>

<footer class="bio"><img src="imgs/susan-75x87-web.jpg" width="75" height="87">I taught in the public schools for 32 years. After retirement, I trained to become a fitness instructor for older adults. I enjoy working on family history, writing novels, and researching the latest fitness news for my class participants.</footer>

<nav class="footer_nav">
<a href="https://susaningraham.net">Susan&thinsp;Ingraham<span class='color_light_gray'>&#8202;.&#8202;net</span></a>
</nav>


</body></html>