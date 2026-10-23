# StudentHub sitemap

```text
StudentHub
├── Sign in (public)
├── Create account (public)
├── Student portal (authenticated)
│   ├── Home
│   ├── Profile
│   ├── Courses
│   ├── Attendance
│   ├── Timetable
│   ├── Assignments
│   ├── Results
│   ├── Notices
│   ├── Help and contact
│   └── Data explorer
│       ├── Events JSON
│       ├── Students JSON
│       └── FAQs JSON
└── Administrator (admin role only)
    ├── Dashboard and statistics
    ├── Student and event management
    ├── Event poster uploads
    ├── MySQL database demonstration
    └── Logout
```

Sign-in routes administrators to the administrator dashboard and students to the student dashboard. Portal HTML pages require an authenticated session; administrator tools additionally verify the admin role on the server.
