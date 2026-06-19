<script lang="ts">
	import '../app.css';
	import favicon from '$lib/assets/favicon.svg';

	let { children } = $props();
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<meta name="description" content="Dashboard - Monitor and manage your infrastructure" />
</svelte:head>

<div class="min-h-screen flex flex-col">
	<header class="navbar bg-base-100 border-b border-base-200 shadow-sm">
		<div class="navbar-start">
			<a href="/" class="btn btn-ghost text-xl font-semibold">Dash</a>
		</div>
		<div class="navbar-end">
			<span class="text-sm text-base-content/60">v0.0.1</span>
		</div>
	</header>

	<main class="flex-1">
		{@render children()}
	</main>

	<footer class="footer footer-center bg-base-200 text-base-content/60 p-4 text-sm">
		<p>Dash Dashboard</p>
	</footer>
</div>
