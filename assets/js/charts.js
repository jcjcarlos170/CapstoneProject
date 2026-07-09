// ================================================================
//  OPTICANA — charts.js
//  All Chart.js initializations. Requires Chart.js global from CDN.
// ================================================================

const ORANGE  = '#E8760A'
const ORANGE2 = '#FF9D3A'
const DARK    = '#1C1C1C'
const GRID    = '#F3F4F6'
const TEAL    = '#0D9488'
const BLUE    = '#2563EB'

// Canvas gradient helpers for bar/line fills
function _makeGrad(ctx, stops, horizontal) {
  const grad = horizontal
    ? ctx.createLinearGradient(0, 0, 420, 0)
    : ctx.createLinearGradient(0, 0, 0, 320)
  stops.forEach(([pos, color]) => grad.addColorStop(pos, color))
  return grad
}
function orangeGrad(ctx, horizontal = false) { return _makeGrad(ctx, [[0,'#FAA84F'],[0.6,'#E8760A'],[1,'#C4620A']], horizontal) }
function greenGrad (ctx, horizontal = false) { return _makeGrad(ctx, [[0,'#6EE7B7'],[0.6,'#10B981'],[1,'#059669']], horizontal) }
function blueGrad  (ctx, horizontal = false) { return _makeGrad(ctx, [[0,'#93C5FD'],[0.6,'#3B82F6'],[1,'#1D4ED8']], horizontal) }
function redGrad   (ctx, horizontal = false) { return _makeGrad(ctx, [[0,'#FCA5A5'],[0.6,'#EF4444'],[1,'#DC2626']], horizontal) }
function tealGrad  (ctx, horizontal = false) { return _makeGrad(ctx, [[0,'#5EEAD4'],[0.6,'#14B8A6'],[1,'#0D9488']], horizontal) }

// Map a hex color string to its gradient. Falls back to the original value if unrecognized.
function makeColorGrad(ctx, hex, horizontal = false) {
  if (!hex || typeof hex !== 'string') return hex
  const h = hex.toLowerCase()
  if (/e87|e88|faa|fa9|f59|c462/.test(h))     return orangeGrad(ctx, horizontal)
  if (/10b9|16a3|059|6ee7|2e7d|a3f/.test(h))   return greenGrad(ctx, horizontal)
  if (/2563|1d4e|3b82|60a5|93c5/.test(h))       return blueGrad(ctx, horizontal)
  if (/dc26|ef44|c628|fca5|f871|b91c/.test(h))  return redGrad(ctx, horizontal)
  if (/0d94|14b8|5eea|0e7a/.test(h))            return tealGrad(ctx, horizontal)
  return hex
}

// Transform solid-color backgroundColor strings/arrays to canvas gradients.
// Call before new Chart(). Returns transformed datasets array.
function applyDatasetGradients(datasets, canvas, horizontal = false) {
  if (!canvas || !datasets) return datasets
  const ctx = canvas.getContext('2d')
  return datasets.map(ds => {
    const bg = ds.backgroundColor
    if (typeof bg === 'string') return { ...ds, backgroundColor: makeColorGrad(ctx, bg, horizontal) }
    if (Array.isArray(bg))      return { ...ds, backgroundColor: bg.map(c => makeColorGrad(ctx, c, horizontal)) }
    return ds
  })
}
window.applyDatasetGradients = applyDatasetGradients

// Center-text plugin for doughnut charts
const centerTextPlugin = {
  id: 'centerText',
  afterDraw(chart) {
    if (chart.config.type !== 'doughnut' || !chart.config.options._centerText) return
    const { ctx, chartArea: { width, height, top, left } } = chart
    const cx = left + width / 2
    const cy = top + height / 2
    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0)
    ctx.save()
    ctx.font = 'bold 22px Poppins, sans-serif'
    ctx.fillStyle = '#1f2937'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(total, cx, cy - 10)
    ctx.font = '11px Poppins, sans-serif'
    ctx.fillStyle = '#9ca3af'
    ctx.fillText('Total', cx, cy + 12)
    ctx.restore()
  }
}
Chart.register(centerTextPlugin)

// Keep references so we can destroy before re-creating
let _charts = {}

function destroy(key) {
  if (_charts[key]) { _charts[key].destroy(); _charts[key] = null }
}

function baseOptions(yLabel = '') {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: DARK,
        titleColor: '#fff',
        bodyColor: '#D1D5DB',
        padding: 10,
        cornerRadius: 8,
        displayColors: false
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#9CA3AF', font: { size: 11 } }
      },
      y: {
        grid: { color: GRID, drawBorder: false },
        ticks: { color: '#9CA3AF', font: { size: 11 } },
        title: yLabel ? { display: true, text: yLabel, color: '#9CA3AF', font: { size: 11 } } : undefined
      }
    }
  }
}

// ── Admin Dashboard — Monthly Appointments (bar) ────────────────
function initAppointmentsChart(canvasId = 'chart-appointments') {
  destroy('appts')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ctx = el.getContext('2d')

  _charts.appts = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
      datasets: [{
        label: 'Appointments',
        data: [18, 22, 28, 25, 32, 38],
        backgroundColor: orangeGrad(ctx),
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      ...baseOptions('Count'),
      plugins: {
        ...baseOptions().plugins,
        legend: { display: false }
      }
    }
  })
}

// ── Admin Dashboard — Patient Growth (line) ─────────────────────
function initPatientGrowthChart(canvasId = 'chart-growth') {
  destroy('growth')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ctx = el.getContext('2d')

  const grad = ctx.createLinearGradient(0, 0, 0, 200)
  grad.addColorStop(0, 'rgba(232,118,10,0.18)')
  grad.addColorStop(1, 'rgba(232,118,10,0)')

  _charts.growth = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
      datasets: [{
        label: 'New Patients',
        data: [8, 12, 15, 14, 18, 20],
        borderColor: ORANGE,
        borderWidth: 2.5,
        backgroundColor: grad,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: ORANGE,
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: baseOptions('Patients')
  })
}

// ── Reports Page — Appointment Status Breakdown (doughnut) ──────
function initReportStatusChart(canvasId = 'chart-report-status') {
  destroy('rStatus')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ctx = el.getContext('2d')

  _charts.rStatus = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Pending', 'Completed', 'Cancelled', 'Disapproved'],
      datasets: [{
        data: [38, 22, 87, 14, 7],
        backgroundColor: ['#10B981', ORANGE, BLUE, '#EF4444', '#9CA3AF'],
        borderWidth: 0,
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'right',
          labels: { color: '#374151', font: { size: 12 }, padding: 12, boxWidth: 12, borderRadius: 3 }
        },
        tooltip: {
          backgroundColor: DARK,
          titleColor: '#fff',
          bodyColor: '#D1D5DB',
          padding: 10,
          cornerRadius: 8
        }
      }
    }
  })
}

// ── Reports Page — Monthly Appointments bar ─────────────────────
function initReportMonthlyChart(canvasId = 'chart-report-monthly') {
  destroy('rMonthly')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ctx = el.getContext('2d')

  _charts.rMonthly = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
      datasets: [
        {
          label: 'Appointments',
          data: [28, 35, 42, 31, 38, 44, 34, 41, 38, 52, 47, 61],
          backgroundColor: orangeGrad(ctx),
          borderRadius: 5,
          borderSkipped: false
        },
        {
          label: 'New Patients',
          data: [8, 12, 15, 9, 11, 14, 12, 18, 14, 22, 19, 28],
          backgroundColor: '#FFF0DC',
          borderRadius: 5,
          borderSkipped: false
        }
      ]
    },
    options: {
      ...baseOptions('Count'),
      plugins: {
        legend: {
          display: true,
          position: 'top',
          align: 'end',
          labels: { color: '#374151', font: { size: 11 }, boxWidth: 12, padding: 12 }
        },
        tooltip: baseOptions().plugins.tooltip
      }
    }
  })
}

// ── Chart update helpers (no destroy/recreate) ───────────────────
function updateAppointmentsChart(data, labels) {
  if (!_charts.appts) return
  if (labels) _charts.appts.data.labels = labels
  _charts.appts.data.datasets[0].data = data
  _charts.appts.update('active')
}

function updatePatientGrowthChart(data, labels) {
  if (!_charts.growth) return
  if (labels) _charts.growth.data.labels = labels
  _charts.growth.data.datasets[0].data = data
  _charts.growth.update('active')
}

// ── Analytics — Appointment Status Doughnut (with center text) ──
function initAnalyticsDoughnut(canvasId = 'chart-analytics-doughnut') {
  destroy('aDoughnut')
  const el = document.getElementById(canvasId)
  if (!el) return
  const d = window.ANALYTICS?.appointmentStatus || { completed: 68, approved: 18, pending: 7, cancelled: 4 }
  _charts.aDoughnut = new Chart(el.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['Completed', 'Approved', 'Pending', 'Cancelled'],
      datasets: [{ data: [d.completed, d.approved, d.pending, d.cancelled],
        backgroundColor: ['#16a34a', BLUE, ORANGE, '#dc2626'],
        borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      _centerText: true,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, titleColor: '#fff', bodyColor: '#D1D5DB', padding: 10, cornerRadius: 8 }
      }
    }
  })
}

// ── Analytics — Monthly Completed vs Cancelled (stacked bar) ────
function initAnalyticsStacked(canvasId = 'chart-analytics-stacked') {
  destroy('aStacked')
  const el = document.getElementById(canvasId)
  if (!el) return
  const a = window.ANALYTICS || {}
  const ctx = el.getContext('2d')
  _charts.aStacked = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: a.monthlyLabels || ['Oct','Nov','Dec','Jan','Feb','Mar'],
      datasets: [
        { label: 'Completed', data: a.monthlyCompleted || [10,12,14,12,16,18],
          backgroundColor: greenGrad(ctx), borderRadius: 4, borderSkipped: false },
        { label: 'Cancelled', data: a.monthlyCancelled || [1,0,1,1,0,1],
          backgroundColor: redGrad(ctx), borderRadius: 4, borderSkipped: false }
      ]
    },
    options: {
      ...baseOptions('Count'),
      plugins: {
        legend: { display: true, position: 'top', align: 'end',
          labels: { color: '#374151', font: { size: 11 }, boxWidth: 10, padding: 12 } },
        tooltip: baseOptions().plugins.tooltip
      }
    }
  })
}

// ── Analytics — Gender Distribution (pie) ───────────────────────
function initGenderChart(canvasId = 'chart-analytics-gender') {
  destroy('aGender')
  const el = document.getElementById(canvasId)
  if (!el) return
  const g = window.ANALYTICS?.genderDistribution || { male: 72, female: 84 }
  _charts.aGender = new Chart(el.getContext('2d'), {
    type: 'pie',
    data: {
      labels: ['Male', 'Female'],
      datasets: [{ data: [g.male, g.female],
        backgroundColor: ['#3b82f6', '#ec4899'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, titleColor: '#fff', bodyColor: '#D1D5DB', padding: 10, cornerRadius: 8 }
      }
    }
  })
}

// ── Analytics — Age Distribution (horizontal bar) ───────────────
function initAgeChart(canvasId = 'chart-analytics-age') {
  destroy('aAge')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ag = window.ANALYTICS?.ageDistribution || { labels: ['0–17','18–30','31–45','46–60','60+'], data: [12,38,45,35,26] }
  _charts.aAge = new Chart(el.getContext('2d'), {
    type: 'bar',
    data: {
      labels: ag.labels,
      datasets: [{ label: 'Patients', data: ag.data,
        backgroundColor: ['#fde8c8','#fbc97a','#E8760A','#c4620a','#9a4d08'],
        borderRadius: 4, borderSkipped: false }]
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, titleColor: '#fff', bodyColor: '#D1D5DB', padding: 10, cornerRadius: 8 }
      },
      scales: {
        x: { grid: { color: GRID }, ticks: { color: '#9CA3AF', font: { size: 11 } }, beginAtZero: true },
        y: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 11 } } }
      }
    }
  })
}

// ── Analytics — Doctor Utilization (horizontal bar) ─────────────
function initDoctorUtilChart(canvasId = 'chart-analytics-doctor') {
  destroy('aDoctor')
  const el = document.getElementById(canvasId)
  if (!el) return
  const docs = window.ANALYTICS?.doctorUtilization || []
  _charts.aDoctor = new Chart(el.getContext('2d'), {
    type: 'bar',
    data: {
      labels: docs.map(d => d.name),
      datasets: [{ label: 'Appointments', data: docs.map(d => d.appointments),
        backgroundColor: orangeGrad(ctx, true), borderRadius: 4, borderSkipped: false }]
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, titleColor: '#fff', bodyColor: '#D1D5DB', padding: 10, cornerRadius: 8 }
      },
      scales: {
        x: { grid: { color: GRID }, ticks: { color: '#9CA3AF', font: { size: 11 } }, beginAtZero: true },
        y: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 12 } } }
      }
    }
  })
}

// ── Analytics — Update all charts on filter apply ────────────────
function updateAnalyticsCharts(ds) {
  if (_charts.aDoughnut && ds.appointmentStatus) {
    const s = ds.appointmentStatus
    _charts.aDoughnut.data.datasets[0].data = [s.completed, s.approved, s.pending, s.cancelled]
    _charts.aDoughnut.update('active')
  }
  if (_charts.aStacked && ds.monthlyCompleted) {
    _charts.aStacked.data.datasets[0].data = ds.monthlyCompleted
    _charts.aStacked.data.datasets[1].data = ds.monthlyCancelled
    _charts.aStacked.update('active')
  }
  if (_charts.appts && ds.monthlyAppointments) {
    _charts.appts.data.datasets[0].data = ds.monthlyAppointments
    _charts.appts.update('active')
  }
  if (_charts.growth && ds.patientGrowth) {
    _charts.growth.data.datasets[0].data = ds.patientGrowth
    _charts.growth.update('active')
  }
}

// ── Staff Dashboard — Today's Overview (horizontal bar) ─────────
function initStaffOverviewChart(canvasId = 'chart-staff-overview') {
  destroy('staffOverview')
  const el = document.getElementById(canvasId)
  if (!el) return
  const ctx = el.getContext('2d')

  _charts.staffOverview = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
      datasets: [{
        label: 'Appointments this week',
        data: [8, 12, 7, 10, 9, 5],
        backgroundColor: orangeGrad(ctx, true),
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      ...baseOptions('Count'),
      indexAxis: 'y',
      scales: {
        x: { grid: { color: GRID }, ticks: { color: '#9CA3AF', font: { size: 11 } } },
        y: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 12 } } }
      }
    }
  })
}

function updateStaffOverviewChart(data, labels) {
  if (!_charts.staffOverview) return
  if (labels) _charts.staffOverview.data.labels = labels
  _charts.staffOverview.data.datasets[0].data = data
  _charts.staffOverview.update('active')
}
