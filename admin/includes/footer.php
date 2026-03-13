    </main>
</div>
<button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
<script>
document.addEventListener('click', function(e) {
    var s = document.getElementById('sidebar');
    var t = document.getElementById('menuToggle');
    if (window.innerWidth <= 768 && s && s.classList.contains('open') && !s.contains(e.target) && t && !t.contains(e.target)) {
        s.classList.remove('open');
    }
});
</script>
</body>
</html>
