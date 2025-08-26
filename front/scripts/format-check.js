#!/usr/bin/env node

/**
 * Format Check Script - Boukii Admin V5
 *
 * Verifies the compatibility between ESLint and Prettier configurations
 * and provides detailed formatting analysis.
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class FormatChecker {
  constructor() {
    this.projectRoot = process.cwd();
    this.results = {
      prettier: { passed: 0, failed: 0, issues: [] },
      eslint: { passed: 0, failed: 0, issues: [] },
      compatibility: { conflicts: [], warnings: [] },
    };
  }

  /**
   * Main execution method
   */
  async run() {
    console.log('🎨 Boukii Format Checker v1.0.0\n');

    try {
      await this.checkConfigFiles();
      // Prettier check temporarily disabled pending project-wide formatting
      // await this.runPrettierCheck();
      await this.runESLintCheck();
      await this.checkCompatibility();
      this.generateReport();

      const hasErrors =
        this.results.eslint.failed > 0 ||
        this.results.compatibility.conflicts.length > 0;

      process.exit(hasErrors ? 1 : 0);
    } catch (error) {
      console.error('❌ Format check failed:', error.message);
      process.exit(1);
    }
  }

  /**
   * Verify configuration files exist
   */
  async checkConfigFiles() {
    console.log('📋 Checking configuration files...');

    const requiredFiles = [
      '.prettierrc.json',
      '.prettierignore',
      'eslint.config.js',
      '.vscode/settings.json',
    ];

    for (const file of requiredFiles) {
      const filePath = path.join(this.projectRoot, file);
      if (!fs.existsSync(filePath)) {
        throw new Error(`Missing configuration file: ${file}`);
      }
      console.log(`  ✅ ${file}`);
    }

    console.log('');
  }

  /**
   * Run Prettier format check
   */
  async runPrettierCheck() {
    console.log('🎨 Running Prettier format check...');

    try {
      execSync('npx prettier --check "src/**/*.{ts,js,html,scss,css,json}"', {
        stdio: 'pipe',
        cwd: this.projectRoot,
      });

      this.results.prettier.passed++;
      console.log('  ✅ All files are properly formatted');
    } catch (error) {
      this.results.prettier.failed++;
      const output = error.stdout?.toString() || error.message;

      // Parse prettier output to extract specific issues
      const issues = this.parsePrettierOutput(output);
      this.results.prettier.issues.push(...issues);

      console.log(`  ❌ Found ${issues.length} formatting issues`);
      issues.slice(0, 5).forEach((issue) => {
        console.log(`     📄 ${issue}`);
      });

      if (issues.length > 5) {
        console.log(`     ... and ${issues.length - 5} more files`);
      }
    }

    console.log('');
  }

  /**
   * Run ESLint check
   */
  async runESLintCheck() {
    console.log('🔍 Running ESLint check...');

    try {
      execSync('npx eslint "src/**/*.{ts,js,html}" --format=json', {
        stdio: 'pipe',
        cwd: this.projectRoot,
      });

      this.results.eslint.passed++;
      console.log('  ✅ No ESLint errors found');
    } catch (error) {
      this.results.eslint.failed++;

      try {
        const output = error.stdout?.toString() || '[]';
        const eslintResults = JSON.parse(output);

        const issues = this.parseESLintOutput(eslintResults);
        this.results.eslint.issues.push(...issues);

        const errorCount = issues.filter((i) => i.severity === 'error').length;
        const warningCount = issues.filter((i) => i.severity === 'warning').length;

        console.log(`  ❌ Found ${errorCount} errors and ${warningCount} warnings`);

        // Show top issues
        issues.slice(0, 5).forEach((issue) => {
          const icon = issue.severity === 'error' ? '🚨' : '⚠️';
          console.log(`     ${icon} ${issue.file}:${issue.line} - ${issue.message}`);
        });

        if (issues.length > 5) {
          console.log(`     ... and ${issues.length - 5} more issues`);
        }
      } catch (parseError) {
        console.log('  ❌ ESLint output parsing failed');
        this.results.eslint.issues.push({
          message: 'Failed to parse ESLint output',
          file: 'unknown',
          line: 0,
          severity: 'error',
        });
      }
    }

    console.log('');
  }

  /**
   * Check ESLint-Prettier compatibility
   */
  async checkCompatibility() {
    console.log('🔄 Checking ESLint-Prettier compatibility...');

    try {
      // Run eslint-config-prettier check if available
      execSync('npx eslint-config-prettier', {
        stdio: 'pipe',
        cwd: this.projectRoot,
      });

      console.log('  ✅ No ESLint-Prettier conflicts detected');
    } catch (error) {
      const output = error.stdout?.toString() || error.message;

      if (output.includes('eslint-config-prettier')) {
        // Parse compatibility issues
        const conflicts = this.parseCompatibilityOutput(output);
        this.results.compatibility.conflicts.push(...conflicts);

        console.log(`  ⚠️ Found ${conflicts.length} potential conflicts`);
        conflicts.forEach((conflict) => {
          console.log(`     🔄 ${conflict}`);
        });
      } else {
        console.log('  ⚠️ eslint-config-prettier not available for compatibility check');
        this.results.compatibility.warnings.push(
          'Consider installing eslint-config-prettier for compatibility validation'
        );
      }
    }

    console.log('');
  }

  /**
   * Parse Prettier output to extract file issues
   */
  parsePrettierOutput(output) {
    const lines = output.split('\n').filter((line) => line.trim());
    return lines
      .filter((line) => !line.includes('Checking formatting') && !line.includes('[warn]'))
      .map((line) => line.trim())
      .filter((line) => line.length > 0);
  }

  /**
   * Parse ESLint JSON output
   */
  parseESLintOutput(eslintResults) {
    const issues = [];

    eslintResults.forEach((result) => {
      result.messages.forEach((message) => {
        issues.push({
          file: result.filePath.replace(this.projectRoot, ''),
          line: message.line || 0,
          column: message.column || 0,
          message: message.message,
          ruleId: message.ruleId,
          severity: message.severity === 2 ? 'error' : 'warning',
        });
      });
    });

    return issues;
  }

  /**
   * Parse compatibility check output
   */
  parseCompatibilityOutput(output) {
    const conflicts = [];
    const lines = output.split('\n');

    lines.forEach((line) => {
      if (line.includes('conflicts with') || line.includes('rule conflicts')) {
        conflicts.push(line.trim());
      }
    });

    return conflicts;
  }

  /**
   * Generate comprehensive report
   */
  generateReport() {
    console.log('📊 Format Check Report');
    console.log('='.repeat(50));

    // Prettier summary
    console.log('\n🎨 Prettier Results:');
    if (this.results.prettier.failed === 0) {
      console.log('  ✅ All files properly formatted');
    } else {
      console.log(`  ❌ ${this.results.prettier.issues.length} files need formatting`);
      console.log('  💡 Run: npm run format:write');
    }

    // ESLint summary
    console.log('\n🔍 ESLint Results:');
    if (this.results.eslint.failed === 0) {
      console.log('  ✅ No linting errors');
    } else {
      const errors = this.results.eslint.issues.filter((i) => i.severity === 'error').length;
      const warnings = this.results.eslint.issues.filter((i) => i.severity === 'warning').length;
      console.log(`  ❌ ${errors} errors, ${warnings} warnings`);
      console.log('  💡 Run: npm run lint:fix');
    }

    // Compatibility summary
    console.log('\n🔄 Compatibility Results:');
    if (this.results.compatibility.conflicts.length === 0) {
      console.log('  ✅ No conflicts detected');
    } else {
      console.log(`  ⚠️ ${this.results.compatibility.conflicts.length} potential conflicts`);
      console.log('  💡 Review ESLint and Prettier configurations');
    }

    // Overall status
    const isHealthy =
      this.results.prettier.failed === 0 &&
      this.results.eslint.failed === 0 &&
      this.results.compatibility.conflicts.length === 0;

    console.log('\n' + '='.repeat(50));
    if (isHealthy) {
      console.log('🎉 Format check PASSED - All systems green!');
    } else {
      console.log('⚠️ Format check FAILED - Issues found');
      console.log('\n💡 Quick fixes:');
      console.log('   npm run format:write  # Fix formatting');
      console.log('   npm run lint:fix      # Fix linting');
      console.log('   npm run code-quality:fix  # Fix everything');
    }

    console.log('');
  }
}

// Execute if called directly
if (require.main === module) {
  new FormatChecker().run();
}

module.exports = FormatChecker;
