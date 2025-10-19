#!/bin/bash

# PC PhotoWall Continuous Testing Script
# This script runs all tests and provides continuous testing capabilities

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TESTS_DIR="$PROJECT_ROOT/tests"
LOG_DIR="$PROJECT_ROOT/logs"
TEST_LOG="$LOG_DIR/test-results.log"

# Create logs directory if it doesn't exist
mkdir -p "$LOG_DIR"

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to check if dev environment is running
check_dev_environment() {
    print_status $BLUE "Prüfe Dev-Umgebung..."
    
    # Check if dev containers are running
    local web_status=$(docker-compose -f "$PROJECT_ROOT/docker-compose.dev.yml" ps web 2>/dev/null | grep -c "Up")
    local db_status=$(docker-compose -f "$PROJECT_ROOT/docker-compose.dev.yml" ps db 2>/dev/null | grep -c "Up")
    
    if [ "$web_status" -eq 0 ] || [ "$db_status" -eq 0 ]; then
        print_status $RED "❌ Dev-Umgebung läuft nicht!"
        print_status $YELLOW "Tests benötigen eine laufende Dev-Umgebung mit Datenbankzugriff."
        print_status $YELLOW "Starte die Dev-Umgebung mit: make dev-up"
        return 1
    fi
    
    print_status $GREEN "✅ Dev-Umgebung läuft"
    return 0
}

# Function to run a single test suite
run_test_suite() {
    local test_file=$1
    local test_name=$2
    
    print_status $BLUE "Running $test_name..."
    
    if php "$test_file" 2>&1 | tee -a "$TEST_LOG"; then
        print_status $GREEN "✅ $test_name passed"
        return 0
    else
        print_status $RED "❌ $test_name failed"
        return 1
    fi
}

# Function to run all tests
run_all_tests() {
    # Check if dev environment is running
    if ! check_dev_environment; then
        exit 1
    fi
    
    local start_time=$(date +%s)
    local failed_tests=0
    
    print_status $BLUE "🧪 Starting PC PhotoWall Test Suite"
    print_status $BLUE "=================================="
    
    # Run comprehensive tests
    if ! run_test_suite "$TESTS_DIR/ComprehensiveTests.php" "Comprehensive Tests"; then
        ((failed_tests++))
    fi
    
    # Run upload functionality tests
    if ! run_test_suite "$TESTS_DIR/UploadFunctionalityTests.php" "Upload Functionality Tests"; then
        ((failed_tests++))
    fi
    
    # Run real image upload tests
    if ! run_test_suite "$TESTS_DIR/RealImageUploadTests.php" "Real Image Upload Tests"; then
        ((failed_tests++))
    fi
    
    # Run image rotation analysis tests
    if ! run_test_suite "$TESTS_DIR/ImageRotationAnalysisTests.php" "Image Rotation Analysis Tests"; then
        ((failed_tests++))
    fi
    
    # Run photo rotation functionality tests
    if ! run_test_suite "$TESTS_DIR/PhotoRotationTests.php" "Photo Rotation Tests"; then
        ((failed_tests++))
    fi
    
    # Run integration tests
    if ! run_test_suite "$TESTS_DIR/SimpleIntegrationTests.php" "Integration Tests"; then
        ((failed_tests++))
    fi
    
    # Run event configuration tests
    if ! run_test_suite "$TESTS_DIR/EventConfigurationTests.php" "Event Configuration Tests"; then
        ((failed_tests++))
    fi
    
    # Run event management tests
    if ! run_test_suite "$TESTS_DIR/EventManagementTests.php" "Event Management Tests"; then
        ((failed_tests++))
    fi
    
    # Run display configuration tests
    if ! run_test_suite "$TESTS_DIR/DisplayConfigurationTests.php" "Display Configuration Tests"; then
        ((failed_tests++))
    fi
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    print_status $BLUE "=================================="
    print_status $BLUE "Test Summary:"
    print_status $BLUE "Duration: ${duration}s"
    
    if [ $failed_tests -eq 0 ]; then
        print_status $GREEN "✅ All tests passed!"
        return 0
    else
        print_status $RED "❌ $failed_tests test suite(s) failed"
        return 1
    fi
}

# Function to run tests in watch mode
run_watch_mode() {
    print_status $YELLOW "👀 Starting watch mode - tests will run on file changes"
    print_status $YELLOW "Press Ctrl+C to stop"
    
    # Check if fswatch is available
    if ! command -v fswatch &> /dev/null; then
        print_status $RED "❌ fswatch not found. Install it for watch mode:"
        print_status $YELLOW "  macOS: brew install fswatch"
        print_status $YELLOW "  Linux: apt-get install fswatch"
        exit 1
    fi
    
    # Watch for changes in PHP files in app/ and tests/
    fswatch -o "$PROJECT_ROOT/app" "$PROJECT_ROOT/tests" --include=".*\.php$" | while read; do
        print_status $BLUE "🔄 File change detected, running tests..."
        run_all_tests
        echo ""
    done
}

# Function to run quick tests (unit tests only)
run_quick_tests() {
    # Check if dev environment is running
    if ! check_dev_environment; then
        exit 1
    fi
    
    print_status $BLUE "⚡ Running quick tests (unit tests only)..."
    run_test_suite "$TESTS_DIR/ComprehensiveTests.php" "Comprehensive Tests"
}

# Function to run syntax check
run_syntax_check() {
    print_status $BLUE "🔍 Running PHP syntax check..."
    
    local syntax_errors=0
    
    # Check all PHP files in app/ and tests/
    find "$PROJECT_ROOT/app" "$PROJECT_ROOT/tests" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" 2>/dev/null | while read file; do
        if ! php -l "$file" > /dev/null 2>&1; then
            print_status $RED "❌ Syntax error in $file"
            ((syntax_errors++))
        fi
    done
    
    if [ $syntax_errors -eq 0 ]; then
        print_status $GREEN "✅ All PHP files have valid syntax"
        return 0
    else
        print_status $RED "❌ $syntax_errors PHP file(s) have syntax errors"
        return 1
    fi
}

# Function to show help
show_help() {
    echo "PC PhotoWall Continuous Testing Script"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  test, all     Run all tests (comprehensive + upload + real-images + rotation-analysis + rotation + integration)"
    echo "  comprehensive Run comprehensive tests (all functions)"
    echo "  upload        Run upload functionality tests (critical upload features)"
    echo "  real-images   Run real image upload tests (with actual image files)"
    echo "  rotation-analysis Run image rotation analysis tests (EXIF data analysis)"
    echo "  rotation      Run photo rotation functionality tests"
    echo "  integration   Run integration tests only"
    echo "  event-config  Run event configuration tests only"
    echo "  event-mgmt    Run event management tests only"
    echo "  display-config Run display configuration tests only"
    echo "  quick         Run quick tests (comprehensive tests only)"
    echo "  syntax        Run PHP syntax check"
    echo "  watch         Run tests in watch mode (requires fswatch)"
    echo "  help          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 test       # Run all tests"
    echo "  $0 watch      # Run tests on file changes"
    echo "  $0 syntax     # Check PHP syntax"
}

# Main script logic
case "${1:-test}" in
    "test"|"all")
        run_all_tests
        ;;
    "comprehensive")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/ComprehensiveTests.php" "Comprehensive Tests"
        ;;
    "upload")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/UploadFunctionalityTests.php" "Upload Functionality Tests"
        ;;
    "real-images")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/RealImageUploadTests.php" "Real Image Upload Tests"
        ;;
    "rotation-analysis")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/ImageRotationAnalysisTests.php" "Image Rotation Analysis Tests"
        ;;
    "rotation")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/PhotoRotationTests.php" "Photo Rotation Tests"
        ;;
    "integration")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/SimpleIntegrationTests.php" "Integration Tests"
        ;;
    "event-config")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/EventConfigurationTests.php" "Event Configuration Tests"
        ;;
    "event-mgmt")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/EventManagementTests.php" "Event Management Tests"
        ;;
    "display-config")
        check_dev_environment || exit 1
        run_test_suite "$TESTS_DIR/DisplayConfigurationTests.php" "Display Configuration Tests"
        ;;
    "quick")
        run_quick_tests
        ;;
    "syntax")
        run_syntax_check
        ;;
    "watch")
        check_dev_environment || exit 1
        run_watch_mode
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        print_status $RED "❌ Unknown command: $1"
        show_help
        exit 1
        ;;
esac
