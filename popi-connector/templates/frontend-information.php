<?php

defined( 'ABSPATH' ) || exit;

$popi_context = POPI_Connector_Frontend::render_context();
if ( 'disabled' === $popi_context['mode'] ) {
	status_header( 404 );
} else {
	status_header( 200 );
}
nocache_headers();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<title><?php echo esc_html( $popi_context['title'] ); ?></title>
	<?php wp_head(); ?>
	<style>
		.popi-connector-page{min-height:100vh;display:grid;place-items:center;padding:32px;background:#f7f8fa;color:#17202a;font:16px/1.65 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
		.popi-connector-card{width:min(720px,100%);box-sizing:border-box;padding:clamp(32px,6vw,64px);background:#fff;border:1px solid #e6e9ed;border-radius:24px;box-shadow:0 20px 60px rgba(23,32,42,.08)}
		.popi-connector-card h1{margin:0 0 20px;font-size:clamp(32px,6vw,56px);line-height:1.08;letter-spacing:-.03em}.popi-connector-card a{color:#1769aa}
	</style>
</head>
<body <?php body_class( 'popi-connector-frontend' ); ?>>
<?php wp_body_open(); ?>
<main class="popi-connector-page">
	<article class="popi-connector-card">
		<h1><?php echo esc_html( $popi_context['title'] ); ?></h1>
		<?php if ( $popi_context['description'] ) : ?>
			<div><?php echo wp_kses_post( $popi_context['description'] ); ?></div>
		<?php elseif ( 'disabled' === $popi_context['mode'] ) : ?>
			<p>Tento WordPress slouží jako redakční systém. Veřejná stránka zde není dostupná.</p>
		<?php else : ?>
			<p>Nový web právě připravujeme.</p>
		<?php endif; ?>
	</article>
</main>
<?php wp_footer(); ?>
</body>
</html>

