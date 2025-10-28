#!/bin/bash

# Development Database Seeder
# Run this to populate your local database with comprehensive test data

echo "🌱 Starting comprehensive database seeding for development..."

# Run the comprehensive seeder
php artisan db:seed --class=ComprehensiveSeeder --force

echo "✅ Development database seeding completed!"
echo "🎉 Your local database now has realistic test data for all features!"
echo ""
echo "📊 What was created:"
echo "  👥 Staff users (caregivers, nurses)"
echo "  💊 Pharmaceutical drugs"
echo "  👴 Residents with medical conditions"
echo "  💉 Medications with proper scheduling"
echo "  📊 Vital signs records (30 days of data)"
echo "  📅 Appointments with healthcare providers"
echo "  👨‍⚕️ Caregiver assignments"
echo "  📋 Assessments with scores"
echo "  🏖️ Leave requests"
echo "  😴 Sleep patterns and records"
echo "  💊 Medication administrations"
echo ""
echo "🔑 Login credentials:"
echo "  Admin: admin@edmondserenity.com / password"
echo "  Caregivers: sarah.johnson@edmondserenity.com / password"
echo "  Nurses: emily.rodriguez@edmondserenity.com / password"
