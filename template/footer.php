<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/js/bootstrap.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.dropdown').forEach(drop => {
          if (drop !== btn.parentElement) drop.classList.remove('open');
        });
        btn.parentElement.classList.toggle('open');
      });
    });
  });
</script>
</body>
</html>