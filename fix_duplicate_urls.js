#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// List of service files and their corrections
const serviceFiles = [
  {
    file: 'front/src/app/features/courses/services/courses.service.ts',
    corrections: [{ from: '/api/v5/courses', to: '/courses' }]
  },
  {
    file: 'front/src/app/features/modules/services/module.service.ts',
    corrections: [{ from: '/api/v5/modules', to: '/modules' }]
  },
  {
    file: 'front/src/app/features/clients/services/clients.service.ts',
    corrections: [{ from: '/api/v5/clients', to: '/clients' }]
  },
  {
    file: 'front/src/app/features/schedules/services/schedule.service.ts',
    corrections: [{ from: '/api/v5/schedules', to: '/schedules' }]
  },
  {
    file: 'front/src/app/features/renting/services/renting.service.ts',
    corrections: [{ from: '/api/v5/rentals', to: '/rentals' }]
  }
];

// Monitoring service has multiple URLs to fix
const monitoringFile = 'front/src/app/features/v5-monitoring/services/monitoring.service.ts';
const monitoringCorrections = [
  { from: "'/api/v5/monitoring/system-stats'", to: "'/monitoring/system-stats'" },
  { from: "'/api/v5/monitoring/performance-comparison'", to: "'/monitoring/performance-comparison'" },
  { from: "'/api/v5/monitoring/alerts'", to: "'/monitoring/alerts'" },
  { from: "'/api/v5/monitoring/performance'", to: "'/monitoring/performance'" },
  { from: "'/api/v5/monitoring/migration-error'", to: "'/monitoring/migration-error'" },
  { from: "`/api/v5/monitoring/school/${schoolId}`", to: "`/monitoring/school/${schoolId}`" },
  { from: "'/api/v5/monitoring/health'", to: "'/monitoring/health'" },
  { from: "'/api/v5/monitoring/cache'", to: "'/monitoring/cache'" }
];

// Auth interceptor cleanup
const authInterceptorCorrections = [
  { from: "'/api/v5/auth/login',", to: "" }, // Remove duplicate
  { from: "'/api/v5/auth/register',", to: "" }, // Remove duplicate  
  { from: "'/api/v5/auth/forgot-password',", to: "" } // Remove duplicate
];

function fixFile(filePath, corrections) {
  const fullPath = path.resolve(filePath);
  
  if (!fs.existsSync(fullPath)) {
    console.log(`❌ File not found: ${filePath}`);
    return;
  }

  let content = fs.readFileSync(fullPath, 'utf8');
  let changed = false;

  corrections.forEach(({ from, to }) => {
    if (content.includes(from)) {
      if (to === "") {
        // Remove the line
        content = content.replace(new RegExp(`\\s*${from.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\n`, 'g'), '');
      } else {
        content = content.replace(new RegExp(from.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), to);
      }
      changed = true;
      console.log(`✅ ${filePath}: ${from} → ${to}`);
    }
  });

  if (changed) {
    fs.writeFileSync(fullPath, content);
    console.log(`💾 Saved: ${filePath}`);
  } else {
    console.log(`⚪ No changes needed: ${filePath}`);
  }
}

console.log('🔧 Fixing duplicate /api/v5/ URLs in frontend services...\n');

// Fix regular services
serviceFiles.forEach(({ file, corrections }) => {
  fixFile(file, corrections);
});

// Fix monitoring service
console.log('\n🔧 Fixing monitoring service URLs...');
fixFile(monitoringFile, monitoringCorrections);

// Fix auth interceptor
console.log('\n🔧 Cleaning up auth interceptor duplicates...');
fixFile('front/src/app/core/interceptors/auth.interceptor.ts', authInterceptorCorrections);

console.log('\n✅ URL fixes completed!');
console.log('\n📋 Summary of changes:');
console.log('- Removed /api/v5/ prefix from service URLs (baseUrlInterceptor will add it)');
console.log('- Cleaned up duplicate entries in auth interceptor');
console.log('- Services now use relative paths like /courses, /clients, etc.');
console.log('\n⚠️  Note: Configuration files (environment.service.ts, config-loader.service.ts) kept as-is');
console.log('⚠️  Note: Test files may need manual review and updating');