<?php
?>

<footer class="vt-footer">
  <span class="vt-footer-copy">&copy; 2025 Viking Transport. Tous droits réservés.</span>

  <div class="vt-footer-links">
	<?php
		if (isset($page))
		{
			echo '<a href="Pages/mentions-legales.php">Mentions légales</a>
			<div class="vt-dot"></div>
			<a href="Pages/cgu.php">CGU</a>';
		}
		else {
			echo '<a href="mentions-legales.php">Mentions légales</a>
			<div class="vt-dot"></div>
			<a href="cgu.php">CGU</a>';
		}
    echo '<div class="vt-dot"></div>
    <a>Spider\'s Web</a>';
	?>
  </div>

  <div class="vt-footer-socials">
    <a href="#" aria-label="Facebook">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
      </svg>
    </a>
    <a href="#" aria-label="Instagram">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
        <circle cx="12" cy="12" r="4"/>
        <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
      </svg>
    </a>
    <a href="#" aria-label="X / Twitter">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4l16 16M4 20L20 4"/>
      </svg>
    </a>
    <a href="#" aria-label="LinkedIn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="2" width="20" height="20" rx="3"/>
        <path d="M8 11v5M8 8v.01M12 16v-5M12 11a3 3 0 0 1 6 0v5"/>
      </svg>
    </a>
  </div>
</footer>