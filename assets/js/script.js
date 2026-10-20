document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.lz-alert[data-autoclose]').forEach(function (alerta) {
    setTimeout(function () {
      alerta.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      alerta.style.opacity = '0';
      alerta.style.transform = 'translateY(-6px)';
      setTimeout(function () { alerta.remove(); }, 400);
    }, 4000);
  });

  document.querySelectorAll('[data-toggle-pass]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var campo = document.getElementById(botao.getAttribute('data-toggle-pass'));
      var mostrar = campo.type === 'password';
      campo.type = mostrar ? 'text' : 'password';
      botao.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
      botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
    });
  });

  var form = document.getElementById('form-reserva');
  if (form) iniciarReserva(form);
});

function iniciarReserva(form) {
  var valorHora = parseFloat(form.getAttribute('data-valor-hora')) || 0;
  var agora = form.getAttribute('data-agora');
  var ocupados = {};
  JSON.parse(form.getAttribute('data-ocupados') || '[]').forEach(function (s) { ocupados[s] = true; });

  var campoData = document.getElementById('data_reserva');
  var campoInicio = document.getElementById('hora_inicio');
  var campoFim = document.getElementById('hora_fim');
  var grade = document.getElementById('grade-horas');
  var aviso = document.getElementById('aviso-horario');
  var botoesCorujao = form.querySelectorAll('[data-corujao]');

  function dois(n) { return String(n).padStart(2, '0'); }

  function somaDias(data, n) {
    var d = new Date(data + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  }

  // Chave "AAAA-MM-DD HH" da k-ésima hora a partir do início
  function slot(data, hora, k) {
    var t = hora + k;
    return somaDias(data, Math.floor(t / 24)) + ' ' + dois(t % 24);
  }

  function livre(data, hora, k) {
    var s = slot(data, hora, k);
    return s > agora && !ocupados[s];
  }

  function maxDuracao(data, hora) {
    var n = 0;
    while (n < 12 && livre(data, hora, n)) n++;
    return n;
  }

  function horaInicio() {
    return campoInicio.value ? parseInt(campoInicio.value, 10) : null;
  }

  function duracao() {
    var h = horaInicio();
    if (h === null || !campoFim.value) return 0;
    return (parseInt(campoFim.value, 10) - h + 24) % 24 || 24;
  }

  function percentual(n) {
    if (n >= 12) return 20;
    if (n >= 10) return 15;
    if (n >= 8) return 10;
    return 0;
  }

  function dinheiro(v) {
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function horasTexto(n) {
    return n + (n === 1 ? ' hora' : ' horas');
  }

  function mostrarAviso(texto) {
    aviso.querySelector('span').textContent = texto;
    aviso.hidden = !texto;
  }

  function desenharGrade() {
    var data = campoData.value;
    var atual = horaInicio();
    if (atual !== null && (!data || !livre(data, atual, 0))) {
      campoInicio.value = '';
      atual = null;
    }

    grade.innerHTML = '';
    for (var h = 0; h < 24; h++) {
      var bt = document.createElement('button');
      bt.type = 'button';
      bt.textContent = dois(h) + ':00';
      bt.setAttribute('data-hora', h);
      var s = data ? slot(data, h, 0) : '';
      if (!data || s <= agora) {
        bt.disabled = true;
        bt.className = 'is-past';
      } else if (ocupados[s]) {
        bt.disabled = true;
        bt.className = 'is-busy';
        bt.title = 'Ocupado';
      }
      if (h === atual) bt.classList.add('active');
      bt.setAttribute('aria-pressed', h === atual ? 'true' : 'false');
      grade.appendChild(bt);
    }
  }

  function montarFim(duracaoDesejada) {
    var h = horaInicio();
    campoFim.innerHTML = '';

    if (h === null) {
      campoFim.add(new Option('Escolha o início primeiro', ''));
      campoFim.disabled = true;
      atualizar();
      return;
    }

    var max = maxDuracao(campoData.value, h);
    for (var n = 1; n <= 12; n++) {
      var texto = dois((h + n) % 24) + ':00' + (h + n >= 24 ? ' (dia seguinte)' : '') + ' · ' + horasTexto(n);
      if (percentual(n)) texto += ' · −' + percentual(n) + '% corujão';
      if (n > max) texto += ' · ocupado';
      var opcao = new Option(texto, dois((h + n) % 24) + ':00');
      opcao.disabled = n > max;
      campoFim.add(opcao);
    }
    campoFim.disabled = false;
    campoFim.selectedIndex = Math.max(0, Math.min(duracaoDesejada || 1, max) - 1);
    atualizar();
  }

  function atualizar() {
    var n = duracao();
    var pct = percentual(n);
    var subtotal = n * valorHora;
    var desconto = Math.round(subtotal * pct) / 100;

    botoesCorujao.forEach(function (bt) {
      bt.classList.toggle('active', n === parseInt(bt.getAttribute('data-corujao'), 10));
    });

    document.getElementById('resumo-duracao').textContent = n ? horasTexto(n) : '—';
    document.getElementById('resumo-subtotal').textContent = dinheiro(subtotal);
    document.getElementById('linha-desconto').hidden = !pct;
    document.getElementById('resumo-pct').textContent = '−' + pct + '%';
    document.getElementById('resumo-desconto').textContent = '− ' + dinheiro(desconto);
    document.getElementById('resumo-total').textContent = dinheiro(subtotal - desconto);
  }

  grade.addEventListener('click', function (e) {
    var bt = e.target.closest('button[data-hora]');
    if (!bt || bt.disabled) return;
    mostrarAviso('');
    var desejada = duracao() || 1;
    campoInicio.value = dois(parseInt(bt.getAttribute('data-hora'), 10)) + ':00';
    desenharGrade();
    montarFim(desejada);
  });

  botoesCorujao.forEach(function (bt) {
    bt.addEventListener('click', function () {
      var n = parseInt(bt.getAttribute('data-corujao'), 10);
      var data = campoData.value;
      mostrarAviso('');

      if (!data) {
        campoData.focus();
        return;
      }

      var h = horaInicio();
      if (h === null) {
        if (!livre(data, 22, 0)) {
          mostrarAviso('As 22:00 já estão ocupadas nesse dia. Escolha o início na grade acima.');
          return;
        }
        h = 22;
        campoInicio.value = '22:00';
        desenharGrade();
      }

      if (maxDuracao(data, h) < n) {
        mostrarAviso('Não há ' + n + ' horas livres seguidas a partir das ' + dois(h) + ':00. Tente outro início.');
        montarFim(duracao() || 1);
        return;
      }
      montarFim(n);
    });
  });

  campoData.addEventListener('change', function () {
    mostrarAviso('');
    var desejada = duracao() || 1;
    desenharGrade();
    montarFim(desejada);
  });

  campoFim.addEventListener('change', function () {
    mostrarAviso('');
    atualizar();
  });

  form.addEventListener('submit', function (e) {
    if (!campoInicio.value || !campoFim.value) {
      e.preventDefault();
      mostrarAviso('Escolha o horário de início e de fim.');
    }
  });

  var fimAntigo = campoFim.getAttribute('data-antigo');
  desenharGrade();
  var inicioAntigo = horaInicio();
  montarFim(inicioAntigo !== null && fimAntigo ? ((parseInt(fimAntigo, 10) - inicioAntigo + 24) % 24 || 24) : 1);
}
