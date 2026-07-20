// ================================================================
//  OPTICANA — db.js
//  Central mock data store. No logic. No imports.
// ================================================================

var patients = []

var doctors = []

var staff = []

var admins = []

var appointments = []

var activityLog = []

function getExamRecords() {
  var result = []
  patients.forEach(function(p) {
    ;(p.examinations || []).forEach(function(e) {
      var od = e.od || {}
      var os = e.os || {}
      var sph = function(v) { return v && v !== '0.00' ? v : '' }
      var cyl = function(v) { return v && v !== '0.00' ? v : '' }
      var rxStr = function(eye) {
        var parts = [eye.sph || '']
        if (eye.cyl && eye.cyl !== '0.00') parts.push(eye.cyl + (eye.axis ? ' ×' + eye.axis : ''))
        if (eye.add && eye.add !== '0.00') parts.push('Add ' + eye.add)
        return parts.filter(Boolean).join(' ')
      }
      result.push({
        id:                  e.id,
        patientId:           p.id,
        patientName:         p.name,
        doctor:              e.doctor || '',
        date:                e.date   || '',
        diagnosis:           e.diagnosis           || '',
        lensType:            e.lensType            || '—',
        lensMaterial:        e.lensMaterial        || '',
        lensCoating:         e.lensCoating         || [],
        frameSelection:      e.frameSelection      || '',
        status:              e.status              || 'completed',
        od:                  od,
        os:                  os,
        iop:                 e.iop                 || {},
        pd:                  e.pd                  || '',
        recommendation:      e.recommendation      || '',
        testResults:         e.testResults         || '',
        prescriptionDetails: e.prescriptionDetails || ('OD: ' + rxStr(od) + ' / OS: ' + rxStr(os)).trim(),
        remarks:             e.remarks             || ''
      })
    })
  })
  result.sort(function(a, b) { return b.date.localeCompare(a.date) })
  return result
}

// Defaults shown until _syncClinicSettings() replaces these with live DB data.
var CLINIC_SERVICES = [
  { id: 1, name: 'Eye Examination',                   description: "A comprehensive assessment of the patient's eye condition to evaluate vision and overall eye health.",                    duration: 30, status: 'active', icon: 'eye' },
  { id: 2, name: 'Vision Screening',                  description: 'A basic check to determine if a patient has possible vision problems that may require further examination.',              duration: 15, status: 'active', icon: 'activity' },
  { id: 3, name: 'Refraction',                        description: "A procedure used to determine the correct lens power needed to improve the patient's vision.",                            duration: 25, status: 'active', icon: 'search' },
  { id: 4, name: 'Diagnosis of Refractive Errors',    description: 'Identification of vision conditions such as nearsightedness, farsightedness, and astigmatism.',                          duration: 25, status: 'active', icon: 'alert-circle' },
  { id: 5, name: 'Prescription of Corrective Lenses', description: "Issuance of eyeglass or contact lens prescriptions based on the patient's vision needs.",                               duration: 20, status: 'active', icon: 'file-text' },
  { id: 6, name: 'Lens Fitting',                      description: 'Adjustment and fitting of lenses to ensure proper alignment, comfort, and visual clarity.',                              duration: 20, status: 'active', icon: 'award' },
  { id: 7, name: 'Optical Frame Selection',           description: 'Assisting patients in choosing frames that fit properly and suit their preferences.',                                    duration: 15, status: 'active', icon: 'archive' },
  { id: 8, name: 'Follow-up Consultation',            description: "Subsequent visits to review the patient's vision condition and assess any changes after treatment or prescription.",     duration: 20, status: 'active', icon: 'refresh-cw' }
]
var _svcNextId = 9

var clinicInfo = {
  name: 'Cana Optical Clinic',
  logoName: 'OPTICANA',
  tagline: 'Clear Vision. Compassionate Care.',
  address: 'Unit 3 Paseo de Carmona, Brgy. Maduya, Carmona, Cavite',
  phone: '0929 663 6080',
  mobile: '0929 663 6080',
  email: 'canaopticalclinic@gmail.com',
  hours: 'Monday – Saturday: 9:00 AM – 5:00 PM',
  tinNo: '123-456-789-000',
  phicNo: '01-123456789-0',
  logoUrl: null
}

var consultationSettings = {
  defaultDuration:         '30 min',
  maxAdvanceBooking:       '3 months',
  minAdvanceBooking:       '1 day',
  maxApptsPerDoctorPerDay: 12,
  morningStart:   '8:00 AM',
  morningEnd:     '12:00 PM',
  afternoonStart: '1:00 PM',
  afternoonEnd:   '5:00 PM',
  lunchBreak:  true,
  clinicDays:  ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']
}

// Parses consultationSettings.minAdvanceBooking ('Same day','1 day','2 days',…)
// into the minimum number of days ahead a patient booking must be made.
// Booking calendars (wizard + Doctor Availability) read this so the rule
// stays in sync the moment admin changes it in Settings — no hardcoded "today".
function minAdvanceDays() {
  const v = (consultationSettings.minAdvanceBooking || '').toLowerCase()
  if (!v || v.includes('same')) return 0
  const m = v.match(/\d+/)
  return m ? parseInt(m[0], 10) : 1
}

function minAdvanceTooltip() {
  const n = minAdvanceDays()
  if (n === 0) return ''
  if (n === 1) return 'Same-day appointments are not available.'
  return `Appointments must be booked at least ${n} days in advance.`
}

// Parses consultationSettings.maxAdvanceBooking ('1 week','2 weeks','1 month',…)
// into the furthest-out date a patient booking calendar should allow,
// relative to `base`. Same sync rationale as minAdvanceDays() above —
// booking calendars read this instead of a hardcoded "+3 months".
function maxAdvanceDate(base) {
  const v    = (consultationSettings.maxAdvanceBooking || '3 months').toLowerCase()
  const m    = v.match(/(\d+)\s*(week|month)/)
  const n    = m ? parseInt(m[1], 10) : 3
  const unit = m ? m[2] : 'month'
  const d = new Date(base)
  if (unit === 'week') d.setDate(d.getDate() + n * 7)
  else                 d.setMonth(d.getMonth() + n)
  return d
}

// Helpers that work on the raw data
function getPatientById(id)     { return patients.find(p => p.id === id) }
function getDoctorById(id)      { return doctors.find(d => d.id === id) }
function getAppointmentById(id) { return appointments.find(a => a.id === id) }

function getAppointmentsForDoctor(doctorId) {
  return appointments.filter(a => a.doctorId === doctorId)
}
function getAppointmentsForPatient(patientId) {
  return appointments.filter(a => a.patientId === patientId)
}
function getTodayAppts() {
  const today = localDateStr()
  return appointments.filter(a => a.date === today)
}

function updateAppointmentStatus(id, status) {
  const a = appointments.find(a => a.id === id)
  if (a) {
    a.status = status
    // Deciding the appointment's status supersedes any reschedule request
    // still attached to it (mirrors the server-side clear in update.php)
    delete a.rescheduleRequest
    window._apptPendingCount = appointments.filter(x => x.status === 'pending').length
    if (window._updateSidebarBadges) window._updateSidebarBadges()
  }
}

function addAppointment(appt) {
  appointments.push(appt)
}

// Local (not UTC) "YYYY-MM-DD HH:MM:SS" wall-clock timestamp. Date#toISOString()
// always converts to UTC first, which silently shifted every client-logged
// timestamp by the local UTC offset (e.g. 8h for the Philippines) — this is
// what addActivityLog() and friends should use instead.
function nowTimestamp() {
  const d = new Date()
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}
function localDateStr(d = new Date()) {
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
window.localDateStr = localDateStr

function addActivityLog(entry) {
  activityLog.unshift(entry)
  fetch('api/activity/log.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...entry, userId: window.state?.user?.dbId ?? null })
  }).catch(() => {})
}

// Build the flat "all users" list for User Management
function getAllUsers() {
  const rows = []
  admins.forEach(u  => rows.push({ ...u, role: 'Admin' }))
  staff.forEach(u   => rows.push({ ...u, role: 'Staff' }))
  doctors.forEach(u => rows.push({ ...u, role: 'Doctor' }))
  patients.forEach(u => rows.push({ ...u, role: 'Patient' }))
  return rows
}

// ── Archived Records (soft-deleted, Admin-only) ────────────────
var archivedRecords = []

function addArchivedRecord(entry) {
  archivedRecords.push(entry)
}

function removeArchivedRecord(id) {
  const idx = archivedRecords.findIndex(r => r.id === id)
  if (idx !== -1) archivedRecords.splice(idx, 1)
}

// ── Contact Messages (public contact-form submissions) ─────────
var contactMessages = []
