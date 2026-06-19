<script>
	import '../app.css';

	let { children } = $props();
</script>

<div class="navbar bg-base-100 shadow-sm mb-4">
	<div class="navbar-start">
		<a href="/" class="btn btn-ghost text-xl">Dash</a>
	</div>
</div>

<main>
	{@render children()}
</main>
