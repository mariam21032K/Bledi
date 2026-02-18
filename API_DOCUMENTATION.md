# Bledi Backend API Documentation

## Overview
RESTful API backend for the Bledi platform - a citizen reporting system for urban irregularities built with Symfony 8.0.5, Doctrine ORM, and JWT authentication.

## Base URL
```
http://localhost:8000/api/v1
```

## Authentication
All endpoints (except `/login` and `/register`) require JWT authentication via the `Authorization` header:
```
Authorization: Bearer {accessToken}
```

### JWT Token Details
- **Algorithm**: RS256
- **Token TTL**: 3600 seconds (1 hour)
- **Refresh Token**: Stored in database for token renewal

---

## Authentication Endpoints

### 1. POST `/api/v1/login`
Login with email and password to obtain JWT tokens.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGc...",
    "refreshToken": "refresh_token_hash",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "firstName": "John",
      "lastName": "Doe",
      "userRole": "CITIZEN",
      "isActive": true
    }
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "error": "Invalid email or password",
  "errorCode": "INVALID_CREDENTIALS"
}
```

---

### 2. POST `/api/v1/register`
Create a new citizen account.

**Request:**
```json
{
  "email": "newuser@example.com",
  "password": "SecurePass123",
  "firstName": "Jane",
  "lastName": "Smith",
  "phone": "+213601234567"
}
```

**Password Requirements:**
- Minimum 8 characters
- At least one uppercase letter
- At least one number

**Response (201 Created):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "accessToken": "eyJhbGc...",
    "refreshToken": "refresh_token_hash",
    "user": {
      "id": 2,
      "email": "newuser@example.com",
      "firstName": "Jane",
      "lastName": "Smith",
      "userRole": "CITIZEN",
      "isActive": true
    }
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "errors": {
    "password": ["Password must contain at least one uppercase letter and one number"]
  }
}
```

---

### 3. GET `/api/v1/me`
Get current authenticated user information.

**Headers:**
```
Authorization: Bearer {accessToken}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "phone": "+213601234567",
    "userRole": "CITIZEN",
    "isActive": true,
    "createdAt": "2025-02-17 10:00:00",
    "updatedAt": "2025-02-17 10:00:00"
  }
}
```

---

### 4. POST `/api/v1/refresh`
Obtain a new access token using a refresh token.

**Request:**
```json
{
  "refreshToken": "refresh_token_hash",
  "userId": 1
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGc...",
    "refreshToken": "new_refresh_token_hash"
  }
}
```

---

### 5. POST `/api/v1/logout`
Invalidate the current refresh token and logout.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## Signalement Endpoints

### 1. GET `/api/v1/signalements`
List all signalements with filtering and pagination.

**Query Parameters:**
- `status` (optional): NEW, IN_PROGRESS, RESOLVED, REJECTED
- `priority` (optional): LOW, MEDIUM, HIGH, URGENT
- `category` (optional): Category ID
- `page` (default: 1)
- `limit` (default: 20, max: 100)

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Broken street light",
      "description": "The street light at intersection is broken",
      "status": "IN_PROGRESS",
      "priority": "HIGH",
      "latitude": 36.7372,
      "longitude": 3.0868,
      "address": "Rue de la Paix, Algiers",
      "category": {
        "id": 1,
        "name": "Street Lighting"
      },
      "user": {
        "id": 1,
        "email": "user@example.com",
        "fullName": "John Doe"
      },
      "mediaCount": 2,
      "interventionCount": 1,
      "createdAt": "2025-02-17 10:00:00",
      "updatedAt": "2025-02-17 14:30:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 45,
    "pages": 3
  }
}
```

---

### 2. GET `/api/v1/signalements/{id}`
Get a single signalement by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Broken street light",
    "description": "The street light at intersection is broken",
    "status": "IN_PROGRESS",
    "priority": "HIGH",
    "latitude": 36.7372,
    "longitude": 3.0868,
    "address": "Rue de la Paix, Algiers",
    "category": {
      "id": 1,
      "name": "Street Lighting"
    },
    "user": {
      "id": 1,
      "email": "user@example.com",
      "fullName": "John Doe"
    },
    "mediaCount": 2,
    "interventionCount": 1,
    "createdAt": "2025-02-17 10:00:00",
    "updatedAt": "2025-02-17 14:30:00"
  }
}
```

---

### 3. POST `/api/v1/signalements`
Create a new signalement (Citizens only).

**Request:**
```json
{
  "title": "Pothole on Main Street",
  "description": "Large pothole that needs urgent repair",
  "categoryId": 2,
  "latitude": 36.7372,
  "longitude": 3.0868,
  "address": "Main Street, Algiers",
  "priority": "HIGH"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Signalement created successfully",
  "data": {
    "id": 50,
    "title": "Pothole on Main Street",
    "status": "NEW",
    "priority": "HIGH",
    "mediaCount": 0,
    "interventionCount": 0,
    "createdAt": "2025-02-17 15:00:00"
  }
}
```

---

### 4. PUT `/api/v1/signalements/{id}`
Update a signalement (Owner can update before resolution).

**Request:**
```json
{
  "title": "Updated title",
  "description": "Updated description",
  "priority": "URGENT",
  "address": "Updated address"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Signalement updated successfully",
  "data": {
    "id": 50,
    "title": "Updated title",
    "status": "NEW",
    "priority": "URGENT",
    "createdAt": "2025-02-17 15:00:00",
    "updatedAt": "2025-02-17 15:05:00"
  }
}
```

---

### 5. DELETE `/api/v1/signalements/{id}`
Delete (soft delete) a signalement.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Signalement deleted successfully"
}
```

---

### 6. PATCH `/api/v1/signalements/{id}/status`
Update signalement status (Agents/Admins only).

**Request:**
```json
{
  "status": "IN_PROGRESS"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Status updated successfully",
  "data": {
    "id": 1,
    "status": "IN_PROGRESS",
    "updatedAt": "2025-02-17 15:10:00"
  }
}
```

---

## Category Endpoints

### 1. GET `/api/v1/categories`
List all active categories.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Street Lighting",
      "description": "Issues related to street lighting",
      "signalementCount": 25,
      "createdAt": "2025-02-17 09:00:00"
    },
    {
      "id": 2,
      "name": "Road Damage",
      "description": "Potholes, cracks, and road deterioration",
      "signalementCount": 48,
      "createdAt": "2025-02-17 09:00:00"
    }
  ],
  "total": 8
}
```

---

### 2. GET `/api/v1/categories/{id}`
Get a single category.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Street Lighting",
    "description": "Issues related to street lighting",
    "signalementCount": 25,
    "createdAt": "2025-02-17 09:00:00"
  }
}
```

---

### 3. POST `/api/v1/categories`
Create a new category (Admins only).

**Request:**
```json
{
  "name": "Waste Management",
  "description": "Issues related to garbage collection and cleanliness"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Category created successfully",
  "data": {
    "id": 9,
    "name": "Waste Management",
    "description": "Issues related to garbage collection and cleanliness",
    "signalementCount": 0,
    "createdAt": "2025-02-17 15:15:00"
  }
}
```

---

### 4. PUT `/api/v1/categories/{id}`
Update a category (Admins only).

**Request:**
```json
{
  "name": "Updated name",
  "description": "Updated description"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Category updated successfully"
}
```

---

### 5. DELETE `/api/v1/categories/{id}`
Delete a category (Admins only).

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Category deleted successfully"
}
```

---

## Media Endpoints

### 1. POST `/api/v1/media/upload`
Upload media file (image or video) for a signalement.

**Request (Form Data):**
- `file`: Binary file (JPEG, PNG, GIF, WebP, MP4, MOV, AVI, MKV - max 50MB)
- `signalementId`: Integer ID of the signalement

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Media uploaded successfully",
  "data": {
    "id": 15,
    "type": "IMAGE",
    "filePath": "/uploads/2025/02/17/pothole_abc123.jpg",
    "fileUrl": "/uploads/2025/02/17/pothole_abc123.jpg",
    "signalementId": 50,
    "size": 2048576,
    "createdAt": "2025-02-17 15:20:00"
  }
}
```

---

### 2. GET `/api/v1/media/{id}/download`
Download a media file.

**Response:** Binary file with appropriate MIME type

---

### 3. DELETE `/api/v1/media/{id}`
Delete (soft delete) a media file.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Media deleted successfully"
}
```

---

### 4. GET `/api/v1/media/signalement/{signalementId}`
List all media for a specific signalement.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "type": "IMAGE",
      "filePath": "/uploads/2025/02/17/pothole_abc123.jpg",
      "fileUrl": "/uploads/2025/02/17/pothole_abc123.jpg",
      "signalementId": 50,
      "size": 2048576,
      "createdAt": "2025-02-17 15:20:00"
    },
    {
      "id": 16,
      "type": "VIDEO",
      "filePath": "/uploads/2025/02/17/damage_video_def456.mp4",
      "fileUrl": "/uploads/2025/02/17/damage_video_def456.mp4",
      "signalementId": 50,
      "size": 15728640,
      "createdAt": "2025-02-17 15:25:00"
    }
  ],
  "total": 2
}
```

---

## Intervention Endpoints

### 1. GET `/api/v1/interventions`
List all interventions with optional filtering.

**Query Parameters:**
- `signalementId` (optional): Filter by signalement ID
- `page` (default: 1)
- `limit` (default: 20, max: 100)

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "signalementId": 1,
      "startDate": "2025-02-17 14:00:00",
      "endDate": "2025-02-17 16:30:00",
      "notes": "Replaced the broken street light with LED fixture",
      "createdAt": "2025-02-17 14:00:00",
      "updatedAt": "2025-02-17 16:35:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 12,
    "pages": 1
  }
}
```

---

### 2. GET `/api/v1/interventions/{id}`
Get a single intervention.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "signalementId": 1,
    "startDate": "2025-02-17 14:00:00",
    "endDate": "2025-02-17 16:30:00",
    "notes": "Replaced the broken street light with LED fixture",
    "createdAt": "2025-02-17 14:00:00",
    "updatedAt": "2025-02-17 16:35:00"
  }
}
```

---

### 3. POST `/api/v1/interventions`
Create a new intervention (Agents/Admins only).

**Request:**
```json
{
  "signalementId": 1,
  "startDate": "2025-02-17T14:00:00Z",
  "endDate": "2025-02-17T16:30:00Z",
  "notes": "Replaced the broken street light with LED fixture"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Intervention created successfully",
  "data": {
    "id": 2,
    "signalementId": 1,
    "startDate": "2025-02-17 14:00:00",
    "endDate": "2025-02-17 16:30:00",
    "notes": "Replaced the broken street light with LED fixture",
    "createdAt": "2025-02-17 14:00:00"
  }
}
```

---

### 4. PUT `/api/v1/interventions/{id}`
Update an intervention (Agents/Admins only).

**Request:**
```json
{
  "endDate": "2025-02-17T17:00:00Z",
  "notes": "Updated notes about the intervention"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Intervention updated successfully"
}
```

---

### 5. DELETE `/api/v1/interventions/{id}`
Delete (soft delete) an intervention.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Intervention deleted successfully"
}
```

---

## User Roles & Permissions

### Role: CITIZEN
- Can create signalements
- Can view/update/delete their own signalements
- Can upload media to their signalements
- Can view public categories
- Cannot modify other users' signalements
- Cannot manage interventions
- Cannot manage categories

### Role: MUNICIPAL_AGENT
- Can view all signalements
- Can create/update interventions
- Can change signalement status
- Can upload media to any signalement
- Cannot delete signalements
- Cannot manage categories

### Role: ADMIN
- Full access to all resources
- Can manage users
- Can manage categories
- Can manage all signalements
- Can manage all interventions
- Can delete any resource

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| INVALID_CREDENTIALS | 401 | Email or password is incorrect |
| WEAK_PASSWORD | 400 | Password does not meet requirements |
| USER_EXISTS | 400 | Email already registered |
| UNAUTHORIZED | 401 | Missing or invalid JWT token |
| FORBIDDEN | 403 | User lacks required permissions |
| NOT_FOUND | 404 | Resource not found |
| BAD_REQUEST | 400 | Invalid request data |
| INTERNAL_ERROR | 500 | Server error |

---

## Error Response Format

```json
{
  "success": false,
  "error": "Human readable error message",
  "errorCode": "ERROR_CODE",
  "details": "Additional details (if applicable)",
  "timestamp": "2025-02-17 15:30:00"
}
```

---

## Audit Logging

All user actions are logged in the `audit_log` table with:
- Action performed (CREATE, UPDATE, DELETE, etc.)
- Entity type and ID
- Changes made (before/after values)
- User who performed action
- Timestamp of action
- IP address (future enhancement)

---

## Rate Limiting

To be implemented: 100 requests per minute per IP address

---

## CORS Configuration

CORS is enabled for cross-origin requests from configured domains. See `config/packages/nelmio_cors.yaml` for configuration.

---

## Security Features

✅ **Implemented:**
- JWT authentication with RS256 algorithm
- Password strength validation (8+ chars, uppercase, number)
- Email validation
- Refresh token mechanism for long sessions
- Audit logging for all actions
- Role-based access control (RBAC)
- Soft deletes for data preservation
- File upload validation (MIME type, size)
- SQL injection protection (via Doctrine ORM)
- CORS protection

⚠️ **To Implement:**
- Rate limiting
- Request signing
- Encrypted file storage
- Two-factor authentication
- OAuth2 integration
- API key management

---

## Database Schema

**Tables:**
- `user` - User accounts with authentication details
- `category` - Signalement categories
- `signalement` - Main irregularity reports
- `media` - Uploaded images/videos
- `intervention` - Municipal responses to signalements
- `notification` - User notifications
- `audit_log` - Action history and compliance log

---

## Testing

Example cURL commands:

**Login:**
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

**Create Signalement:**
```bash
curl -X POST http://localhost:8000/api/v1/signalements \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
    "title":"Broken light",
    "description":"Street light is broken",
    "categoryId":1,
    "latitude":36.7372,
    "longitude":3.0868,
    "address":"Main St"
  }'
```

**Upload Media:**
```bash
curl -X POST http://localhost:8000/api/v1/media/upload \
  -H "Authorization: Bearer {accessToken}" \
  -F "file=@photo.jpg" \
  -F "signalementId=50"
```

---

## Version History

- **v1.0.0** (2025-02-17): Initial API release with authentication, signalements, categories, media, and interventions management
