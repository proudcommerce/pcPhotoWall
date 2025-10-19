<?php
/**
 * Simple Test Runner for PC PhotoWall
 *
 * This is a lightweight testing framework for continuous testing
 * of critical functionality without external dependencies.
 */

class TestRunner {
    private $tests = [];
    private $results = [];
    private $verbose = false;
    
    public function __construct($verbose = false) {
        $this->verbose = $verbose;
    }
    
    public function addTest($name, $callback) {
        $this->tests[$name] = $callback;
    }
    
    public function runAll() {
        $this->results = [];
        $passed = 0;
        $failed = 0;
        
        echo "🧪 Running PC PhotoWall Tests...\n\n";
        
        foreach ($this->tests as $name => $callback) {
            try {
                $start = microtime(true);
                $result = $callback();
                $duration = round((microtime(true) - $start) * 1000, 2);
                
                if ($result === true) {
                    echo "✅ {$name} ({$duration}ms)\n";
                    $passed++;
                } else {
                    echo "❌ {$name}: {$result}\n";
                    $failed++;
                }
                
                $this->results[$name] = [
                    'status' => $result === true ? 'PASS' : 'FAIL',
                    'message' => $result === true ? 'OK' : $result,
                    'duration' => $duration
                ];
                
            } catch (Exception $e) {
                echo "💥 {$name}: Exception - {$e->getMessage()}\n";
                $failed++;
                $this->results[$name] = [
                    'status' => 'ERROR',
                    'message' => $e->getMessage(),
                    'duration' => 0
                ];
            }
        }
        
        echo "\n📊 Test Results: {$passed} passed, {$failed} failed\n";
        
        if ($failed > 0) {
            echo "❌ Some tests failed!\n";
            return false;
        } else {
            echo "✅ All tests passed!\n";
            return true;
        }
    }
    
    public function getResults() {
        return $this->results;
    }
}

// Test assertion functions
function assertTrue($condition, $message = '') {
    if (!$condition) {
        throw new Exception($message ?: 'Assertion failed: expected true');
    }
    return true;
}

function assertFalse($condition, $message = '') {
    if ($condition) {
        throw new Exception($message ?: 'Assertion failed: expected false');
    }
    return true;
}

function assertEquals($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        throw new Exception($message ?: "Assertion failed: expected '{$expected}', got '{$actual}'");
    }
    return true;
}

function assertNotEquals($expected, $actual, $message = '') {
    if ($expected === $actual) {
        throw new Exception($message ?: "Assertion failed: expected not '{$expected}', got '{$actual}'");
    }
    return true;
}

function assertContains($needle, $haystack, $message = '') {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message ?: "Assertion failed: '{$haystack}' does not contain '{$needle}'");
    }
    return true;
}

function assertFileExists($file, $message = '') {
    if (!file_exists($file)) {
        throw new Exception($message ?: "Assertion failed: file '{$file}' does not exist");
    }
    return true;
}

function assertFileNotExists($file, $message = '') {
    if (file_exists($file)) {
        throw new Exception($message ?: "Assertion failed: file '{$file}' exists");
    }
    return true;
}
