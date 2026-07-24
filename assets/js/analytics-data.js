// ================================================================
//  CANAOPTICALCLINIC — analytics-data.js
//  Centralized mock data for descriptive analytics.
//  Loaded as a global script before the ES module.
// ================================================================

window.ANALYTICS = {
  summary: {
    totalPatients:          156,
    totalAppointments:      97,
    todaysAppointments:     5,
    completedConsultations: 68,
    cancelledAppointments:  4,
    doctorsAvailable:       '3/5',
    totalVisits:            342,
    prescriptionsIssued:    189,
    avgAppointmentsPerDay:  4.2,
    cancellationRate:       '4.1%'
  },

  monthlyLabels:       ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
  monthlyAppointments: [18, 22, 28, 25, 32, 38],
  patientGrowth:       [8, 12, 15, 14, 18, 20],

  appointmentStatus: { completed: 68, approved: 18, pending: 7, cancelled: 4 },

  genderDistribution: { male: 72, female: 84 },

  ageDistribution: {
    labels: ['0–17', '18–30', '31–45', '46–60', '60+'],
    data:   [12, 38, 45, 35, 26]
  },

  doctorUtilization: [
    { name: 'Dr. Lalaine Cana',         appointments: 28, completionRate: '96%',  avgPerWeek: 4.7 },
    { name: 'Dr. Ruziel Palaje',         appointments: 22, completionRate: '91%',  avgPerWeek: 3.7 },
    { name: 'Dr. Christian Garabiles',   appointments: 18, completionRate: '94%',  avgPerWeek: 3.0 },
    { name: 'Dr. Carmen Sumaya',         appointments: 16, completionRate: '100%', avgPerWeek: 2.7 },
    { name: 'Dr. Julianne Rosche Cana', appointments: 13, completionRate: '92%',  avgPerWeek: 2.2 }
  ],

  monthlyCompleted:  [10, 12, 14, 12, 16, 18],
  monthlyCancelled:  [1, 0, 1, 1, 0, 1],

  cancellationLog: [
    { date: 'Mar 20, 2026', patient: 'Carlo Mendoza',  doctor: 'Dr. Lalaine Cana',       reason: 'Schedule conflict' },
    { date: 'Mar 19, 2026', patient: 'Rosa Dela Cruz', doctor: 'Dr. Christian Garabiles', reason: 'Personal reasons'  },
    { date: 'Feb 28, 2026', patient: 'Jose Reyes',     doctor: 'Dr. Ruziel Palaje',       reason: 'Rescheduled'       },
    { date: 'Feb 15, 2026', patient: 'Ana Garcia',     doctor: 'Dr. Carmen Sumaya',       reason: 'No show'           }
  ],

  // 3 mock datasets rotated on filter apply
  filterDatasets: [
    {
      monthlyAppointments: [18, 22, 28, 25, 32, 38],
      patientGrowth:       [8, 12, 15, 14, 18, 20],
      appointmentStatus:   { completed: 68, approved: 18, pending: 7, cancelled: 4 },
      monthlyCompleted:    [10, 12, 14, 12, 16, 18],
      monthlyCancelled:    [1, 0, 1, 1, 0, 1]
    },
    {
      monthlyAppointments: [12, 18, 22, 19, 28, 31],
      patientGrowth:       [5, 9, 11, 10, 15, 17],
      appointmentStatus:   { completed: 52, approved: 14, pending: 5, cancelled: 3 },
      monthlyCompleted:    [8, 9, 11, 9, 12, 14],
      monthlyCancelled:    [0, 1, 1, 0, 1, 0]
    },
    {
      monthlyAppointments: [22, 26, 31, 29, 35, 42],
      patientGrowth:       [10, 14, 17, 16, 21, 24],
      appointmentStatus:   { completed: 79, approved: 21, pending: 9, cancelled: 5 },
      monthlyCompleted:    [12, 14, 16, 14, 18, 21],
      monthlyCancelled:    [1, 1, 2, 1, 1, 2]
    }
  ]
}
