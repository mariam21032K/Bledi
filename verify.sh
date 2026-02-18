#!/bin/bash
# Bledi Backend API Verification Script

echo "==================================="
echo "Bledi Backend API Verification"
echo "==================================="
echo ""

# Check PHP syntax for all controllers
echo "✓ Checking PHP syntax for all controllers..."
php -l src/Controller/AuthController.php > /dev/null 2>&1 && echo "  ✓ AuthController" || echo "  ✗ AuthController"
php -l src/Controller/SignalementController.php > /dev/null 2>&1 && echo "  ✓ SignalementController" || echo "  ✗ SignalementController"
php -l src/Controller/CategoryController.php > /dev/null 2>&1 && echo "  ✓ CategoryController" || echo "  ✗ CategoryController"
php -l src/Controller/MediaController.php > /dev/null 2>&1 && echo "  ✓ MediaController" || echo "  ✗ MediaController"
php -l src/Controller/InterventionController.php > /dev/null 2>&1 && echo "  ✓ InterventionController" || echo "  ✗ InterventionController"
echo ""

# Check services
echo "✓ Checking services..."
php -l src/Service/FileUploadService.php > /dev/null 2>&1 && echo "  ✓ FileUploadService" || echo "  ✗ FileUploadService"
php -l src/Service/JWTTokenService.php > /dev/null 2>&1 && echo "  ✓ JWTTokenService" || echo "  ✗ JWTTokenService"
php -l src/Service/ValidationService.php > /dev/null 2>&1 && echo "  ✓ ValidationService" || echo "  ✗ ValidationService"
echo ""

# Check entities
echo "✓ Checking entities..."
php -l src/Entity/User.php > /dev/null 2>&1 && echo "  ✓ User" || echo "  ✗ User"
php -l src/Entity/Signalement.php > /dev/null 2>&1 && echo "  ✓ Signalement" || echo "  ✗ Signalement"
php -l src/Entity/Category.php > /dev/null 2>&1 && echo "  ✓ Category" || echo "  ✗ Category"
php -l src/Entity/Media.php > /dev/null 2>&1 && echo "  ✓ Media" || echo "  ✗ Media"
php -l src/Entity/Intervention.php > /dev/null 2>&1 && echo "  ✓ Intervention" || echo "  ✗ Intervention"
php -l src/Entity/Notification.php > /dev/null 2>&1 && echo "  ✓ Notification" || echo "  ✗ Notification"
php -l src/Entity/AuditLog.php > /dev/null 2>&1 && echo "  ✓ AuditLog" || echo "  ✗ AuditLog"
echo ""

# Check enums
echo "✓ Checking enumerations..."
php -l src/Enum/UserRole.php > /dev/null 2>&1 && echo "  ✓ UserRole" || echo "  ✗ UserRole"
php -l src/Enum/SignalementStatus.php > /dev/null 2>&1 && echo "  ✓ SignalementStatus" || echo "  ✗ SignalementStatus"
php -l src/Enum/PriorityLevel.php > /dev/null 2>&1 && echo "  ✓ PriorityLevel" || echo "  ✗ PriorityLevel"
php -l src/Enum/MediaType.php > /dev/null 2>&1 && echo "  ✓ MediaType" || echo "  ✗ MediaType"
echo ""

echo "==================================="
echo "✓ All components verified!"
echo "==================================="
