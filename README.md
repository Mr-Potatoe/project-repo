# Full-Stack Project Repository

A modern full-stack application using Next.js 14 and PHP.

## Tech Stack

### Frontend
- Next.js 14 (App Router)
- Tailwind CSS
- Next-Auth for OAuth authentication
- TypeScript

### Backend
- PHP 8.x (XAMPP)
- MySQL 8.x
- RESTful API

## Project Structure
```
project-repo/
├── frontend/           # Next.js application
├── backend/           # PHP API
├── database/         # SQL scripts
└── docs/            # Documentation
```

## Setup Instructions

### Prerequisites
1. Node.js (Latest LTS version)
2. XAMPP with PHP 8.x
3. MySQL 8.x
4. ngrok for API tunneling

### Frontend Setup
1. Navigate to frontend directory
```bash
cd frontend
npm install
npm run dev
```

### Backend Setup
1. Start XAMPP
2. Place backend files in htdocs
3. Import database schema
4. Start ngrok tunnel

### Environment Variables
Create `.env.local` in frontend directory with:
```
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=your-secret-key
API_URL=your-ngrok-url
```

## Development Workflow
1. Run frontend dev server
2. Start XAMPP services
3. Start ngrok tunnel
4. Begin development!




### curl -X POST http://localhost:3000/api/auth/register -H Content-Type: application/json -d {"email":"admin@example.com","password":"admin123","username":"admin","role":"admin"}

<!-- 
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=your-secret-key
API_URL=http://localhost/project-repo/backend

NEXT_PUBLIC_API_URL=http://localhost
API_URL=http://localhost/project-repo/backend -->