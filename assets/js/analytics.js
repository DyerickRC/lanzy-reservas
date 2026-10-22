document.addEventListener('DOMContentLoaded', function () {
  var fonte = document.getElementById('dados-analytics');
  if (!fonte || !window.Chart) return;
  var dados = JSON.parse(fonte.textContent);

  // Cores validadas para o fundo escuro do painel (PCs, consoles e série única)
  var COR = {
    pc: '#0fa3c2',
    console: '#d93a8c',
    serie: '#7c5cf0',
    superficie: '#131626',
    texto: '#8b91ad',
    grade: 'rgba(255, 255, 255, 0.06)',
    eixo: 'rgba(255, 255, 255, 0.14)',
  };

  Chart.defaults.color = COR.texto;
  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = 400;

  var tooltip = {
    backgroundColor: '#0b0d17',
    borderColor: 'rgba(255, 255, 255, 0.12)',
    borderWidth: 1,
    padding: 10,
    titleColor: '#eef0fa',
    bodyColor: '#c3c7dc',
    footerColor: '#eef0fa',
    boxPadding: 4,
    usePointStyle: true,
  };

  var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  var moedaCurta = function (v) {
    return v >= 1000 ? 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : 'R$ ' + v.toLocaleString('pt-BR');
  };
  var horas = function (v) { return v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' h'; };

  function eixoValor(formatar) {
    return {
      beginAtZero: true,
      grid: { color: COR.grade, drawTicks: false },
      border: { display: false },
      ticks: { padding: 8, maxTicksLimit: 6, callback: formatar },
    };
  }
  var eixoCategoria = {
    grid: { display: false },
    border: { color: COR.eixo },
    ticks: { padding: 6, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 },
  };

  function barra(cor, extra) {
    return Object.assign({
      backgroundColor: cor,
      hoverBackgroundColor: cor,
      borderRadius: 4,
      borderSkipped: 'start',
      maxBarThickness: 32,
      categoryPercentage: 0.82,
      barPercentage: 0.9,
    }, extra || {});
  }

  // Faturamento: barras empilhadas PCs + consoles, com 2px de respiro entre os segmentos
  new Chart(document.getElementById('grafico-faturamento'), {
    type: 'bar',
    data: {
      labels: dados.faturamento.rotulos,
      datasets: [
        barra(COR.pc, { label: 'PCs', data: dados.faturamento.pc, borderColor: COR.superficie, borderWidth: { top: 2 }, borderRadius: 0 }),
        barra(COR.console, { label: 'Consoles', data: dados.faturamento.console, borderColor: COR.superficie, borderWidth: { top: 2 } }),
      ],
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      scales: { x: Object.assign({ stacked: true }, eixoCategoria), y: Object.assign({ stacked: true }, eixoValor(moedaCurta)) },
      plugins: {
        legend: { display: false },
        tooltip: Object.assign({}, tooltip, {
          callbacks: {
            label: function (c) { return ' ' + c.dataset.label + ': ' + moeda.format(c.parsed.y); },
            footer: function (itens) { return 'Total: ' + moeda.format(itens.reduce(function (s, i) { return s + i.parsed.y; }, 0)); },
          },
        }),
      },
    },
  });

  function serieUnica(id, rotulos, valores, nome, formatar, formatarEixo) {
    new Chart(document.getElementById(id), {
      type: 'bar',
      data: { labels: rotulos, datasets: [barra(COR.serie, { label: nome, data: valores })] },
      options: {
        scales: { x: eixoCategoria, y: eixoValor(formatarEixo || formatar) },
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltip, {
            displayColors: false,
            callbacks: { label: function (c) { return nome + ': ' + formatar(c.parsed.y); } },
          }),
        },
      },
    });
  }

  serieUnica('grafico-movimento', dados.movimento.rotulos, dados.movimento.horas, 'Horas com movimento', horas);
  serieUnica('grafico-novos', dados.novos.rotulos, dados.novos.valores, 'Novos clientes', function (v) { return v.toLocaleString('pt-BR'); });
  serieUnica('grafico-semana', dados.semana.rotulos, dados.semana.valores, 'Faturamento médio', function (v) { return moeda.format(v); }, moedaCurta);

  // Horas por máquina: barras horizontais, cor pelo tipo da estação
  new Chart(document.getElementById('grafico-maquinas'), {
    type: 'bar',
    data: {
      labels: dados.maquinas.rotulos,
      datasets: [barra(dados.maquinas.cores, { label: 'Horas vendidas', data: dados.maquinas.horas, borderSkipped: 'start', maxBarThickness: 22 })],
    },
    options: {
      indexAxis: 'y',
      scales: {
        x: eixoValor(horas),
        y: { grid: { display: false }, border: { color: COR.eixo }, ticks: { padding: 8, color: '#c3c7dc' } },
      },
      plugins: {
        legend: { display: false },
        tooltip: Object.assign({}, tooltip, {
          displayColors: false,
          callbacks: { label: function (c) { return 'Horas vendidas: ' + horas(c.parsed.x); } },
        }),
      },
    },
  });
});
