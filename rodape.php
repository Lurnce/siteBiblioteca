</main>
<footer class="rodape">Biblioteca da Escola · <?= date('Y') ?></footer>
<script>
// Pede confirmação em formulários com data-confirm
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { ev.preventDefault(); }
});
// Fecha os avisos (sozinhos após alguns segundos)
document.querySelectorAll('.toast').forEach(function (t) {
  var fechar = function () { t.classList.add('saindo'); setTimeout(function () { t.remove(); }, 250); };
  t.querySelector('.toast-fechar').addEventListener('click', fechar);
  setTimeout(fechar, 6000);
});
</script>
</body>
</html>
