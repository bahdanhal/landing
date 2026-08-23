/**
 * Regex Dialect Transpiler & Compatibility Matrix Engine
 * StackHal Developer Toolbox - Client-side engine
 */

(function () {
  'use strict';

  const ENGINES = {
    pcre: {
      id: 'pcre',
      name: 'PCRE / PCRE2',
      ecosystem: 'PHP, Nginx, Apache, C/C++',
      timeComplexity: 'Exponential worst-case (Backtracking / ReDoS prone)',
      isLinearTime: false,
      supportsLookaround: true,
      supportsBackreferences: true,
      supportsAtomicGroups: true,
      supportsPossessiveQuantifiers: true,
      supportsRecursion: true,
      namedGroupSyntax: ['(?P<name>...)', '(?<name>...)'],
    },
    go_re2: {
      id: 'go_re2',
      name: 'Go RE2',
      ecosystem: 'Golang, Kubernetes, Docker, Envoy',
      timeComplexity: 'Guaranteed O(N) Linear time (DFA/NFA, Zero ReDoS)',
      isLinearTime: true,
      supportsLookaround: false,
      supportsBackreferences: false,
      supportsAtomicGroups: false,
      supportsPossessiveQuantifiers: false,
      supportsRecursion: false,
      namedGroupSyntax: ['(?P<name>...)'],
    },
    javascript: {
      id: 'javascript',
      name: 'JavaScript (ECMAScript)',
      ecosystem: 'V8, Node.js, Web Browsers',
      timeComplexity: 'Exponential worst-case (Backtracking)',
      isLinearTime: false,
      supportsLookaround: true,
      supportsBackreferences: true,
      supportsAtomicGroups: false,
      supportsPossessiveQuantifiers: false,
      supportsRecursion: false,
      namedGroupSyntax: ['(?<name>...)'],
    },
    python: {
      id: 'python',
      name: 'Python re',
      ecosystem: 'Python 3 Standard Library',
      timeComplexity: 'Exponential worst-case (Backtracking)',
      isLinearTime: false,
      supportsLookaround: true,
      supportsBackreferences: true,
      supportsAtomicGroups: false,
      supportsPossessiveQuantifiers: false,
      supportsRecursion: false,
      namedGroupSyntax: ['(?P<name>...)'],
    },
    rust: {
      id: 'rust',
      name: 'Rust regex',
      ecosystem: 'Rust crate, Ripgrep',
      timeComplexity: 'Guaranteed O(N) Linear time (Zero ReDoS)',
      isLinearTime: true,
      supportsLookaround: false,
      supportsBackreferences: false,
      supportsAtomicGroups: false,
      supportsPossessiveQuantifiers: false,
      supportsRecursion: false,
      namedGroupSyntax: ['(?P<name>...)'],
    },
  };

  const DIAGNOSTIC_CODES = {
    ERR_UNSUPPORTED_LOOKAROUND: {
      severity: 'error',
      title: 'Lookaround Assertions Forbidden',
      description:
        'Lookahead (?=...), (?!...) and Lookbehind (?<=...), (?<!...) are not supported in RE2 / Rust linear engines to guarantee O(N) time and prevent ReDoS attacks.',
    },
    ERR_UNSUPPORTED_BACKREFERENCE: {
      severity: 'error',
      title: 'Backreferences Forbidden',
      description:
        'Backreferences (\\1, \\k<name>) require non-deterministic backtracking and are forbidden in DFA/RE2 engines.',
    },
    ERR_UNSUPPORTED_RECURSION: {
      severity: 'error',
      title: 'Pattern Recursion Unsupported',
      description:
        'Recursive subroutine calls (?R), (?1) are exclusive to PCRE and cannot be compiled in RE2, JS, or standard Python.',
    },
    WARN_ATOMIC_GROUP_CONVERTED: {
      severity: 'warning',
      title: 'Atomic Group Converted to Non-Capturing',
      description:
        'Atomic group (?>...) was converted to non-capturing group (?:...) because RE2 does not backtrack.',
    },
    WARN_POSSESSIVE_QUANTIFIER_CONVERTED: {
      severity: 'warning',
      title: 'Possessive Quantifier Simplified',
      description:
        'Possessive quantifier (++ or *+) was simplified to greedy quantifier (+ or *) in RE2.',
    },
    WARN_NAMED_GROUP_SYNTAX_TRANSPILLED: {
      severity: 'warning',
      title: 'Named Group Syntax Transpiled',
      description:
        'Named capture group syntax was adjusted to match the target engine convention.',
    },
    INFO_LINEAR_TIME_GUARANTEED: {
      severity: 'info',
      title: 'Linear Execution Guaranteed',
      description:
        'Target engine operates with guaranteed linear O(N) execution time with zero ReDoS risk.',
    },
  };

  const PRESETS = {
    password_lookahead: {
      sourceEngine: 'pcre',
      targetEngine: 'go_re2',
      pattern: '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)[a-zA-Z\\d]{8,}$',
      testText: 'PassWord123',
    },
    named_log_parser: {
      sourceEngine: 'javascript',
      targetEngine: 'go_re2',
      pattern: '^(?<ip>\\S+) \\[(?<timestamp>[^\\]]+)\\] "(?<method>[A-Z]+) (?<path>\\S+)"',
      testText: '192.168.1.1 [24/Aug/2026:00:40:00 +0000] "GET /api/v1/health"',
    },
    quoted_string_possessive: {
      sourceEngine: 'pcre',
      targetEngine: 'go_re2',
      pattern: '"(?:[^"\\\\]|\\\\.)*+"',
      testText: '"Hello \\"World\\", welcome to StackHal!"',
    },
    multiline_case_insensitive: {
      sourceEngine: 'pcre',
      targetEngine: 'go_re2',
      pattern: '(?i)^[a-z][a-z0-9_-]{2,31}$',
      testText: 'Alpha_Beta-42',
    },
  };

  /**
   * Core Regex Dialect Transpiler & Analyzer
   */
  function transpileRegex(pattern, sourceEngineKey, targetEngineKey) {
    const trimmed = (pattern || '').trim();
    const sourceEngine = ENGINES[sourceEngineKey] || ENGINES.pcre;
    const targetEngine = ENGINES[targetEngineKey] || ENGINES.go_re2;

    const matrix = Object.keys(ENGINES).map((key) =>
      evaluateEngine(trimmed, sourceEngine, ENGINES[key])
    );

    const targetResult = evaluateEngine(trimmed, sourceEngine, targetEngine);

    return {
      sourceEngine: sourceEngineKey,
      targetEngine: targetEngineKey,
      sourcePattern: trimmed,
      transpiledPattern: targetResult.transpiledPattern,
      isCompatible: targetResult.isCompatible,
      diagnostics: targetResult.diagnostics,
      errors: targetResult.diagnostics.filter((d) => d.severity === 'error'),
      warnings: targetResult.diagnostics.filter((d) => d.severity === 'warning'),
      matrix,
    };
  }

  function evaluateEngine(pattern, sourceEngine, targetEngine) {
    if (!pattern) {
      return {
        engine: targetEngine.id,
        engineName: targetEngine.name,
        ecosystem: targetEngine.ecosystem,
        isCompatible: true,
        isLinearTime: targetEngine.isLinearTime,
        transpiledPattern: '',
        diagnostics: targetEngine.isLinearTime
          ? [DIAGNOSTIC_CODES.INFO_LINEAR_TIME_GUARANTEED]
          : [],
      };
    }

    const diagnostics = [];
    let errorsFound = false;

    let hasLookaround = false;
    let hasBackreference = false;
    let hasRecursion = false;
    let hasAtomicGroup = false;
    let hasPossessiveQuantifier = false;
    let hasNamedGroupTranspiled = false;

    const length = pattern.length;
    let i = 0;
    let inCharClass = false;
    let output = '';

    while (i < length) {
      const char = pattern[i];

      // Handle backslash escape
      if (char === '\\') {
        if (i + 1 >= length) {
          output += '\\';
          i++;
          break;
        }

        const nextChar = pattern[i + 1];

        // Inside character class: literal escape
        if (inCharClass) {
          output += '\\' + nextChar;
          i += 2;
          continue;
        }

        // Backreferences: \1 .. \9
        if (/^[1-9]$/.test(nextChar)) {
          hasBackreference = true;
          if (!targetEngine.supportsBackreferences) {
            errorsFound = true;
          }
          output += '\\' + nextChar;
          i += 2;
          continue;
        }

        // Named backreference \k<name> or \k'name'
        if (nextChar === 'k' && i + 2 < length) {
          const delim = pattern[i + 2];
          if (delim === '<' || delim === "'") {
            hasBackreference = true;
            if (!targetEngine.supportsBackreferences) {
              errorsFound = true;
            }
          }
        }

        output += '\\' + nextChar;
        i += 2;
        continue;
      }

      // Handle character class entrance / exit
      if (char === '[' && !inCharClass) {
        inCharClass = true;
        output += '[';
        i++;
        if (i < length && pattern[i] === '^') {
          output += '^';
          i++;
        }
        if (i < length && pattern[i] === ']') {
          output += ']';
          i++;
        }
        continue;
      }

      if (char === ']' && inCharClass) {
        inCharClass = false;
        output += ']';
        i++;

        // Check possessive quantifier after character class e.g. [a-z]++
        const pq = checkPossessiveQuantifier(pattern, i, targetEngine);
        if (pq) {
          output += pq.output;
          i = pq.newIndex;
          if (pq.converted) hasPossessiveQuantifier = true;
        }
        continue;
      }

      if (inCharClass) {
        output += char;
        i++;
        continue;
      }

      // Handle group opening: (
      if (char === '(') {
        if (i + 1 < length && pattern[i + 1] === '?') {
          // Lookarounds
          if (
            pattern.startsWith('(?=', i) ||
            pattern.startsWith('(?!', i) ||
            pattern.startsWith('(?<=', i) ||
            pattern.startsWith('(?<!', i)
          ) {
            hasLookaround = true;
            if (!targetEngine.supportsLookaround) {
              errorsFound = true;
            }
            output += '(';
            i++;
            continue;
          }

          // Recursion (?R), (?0), (?1)
          if (
            pattern.startsWith('(?R)', i) ||
            pattern.startsWith('(?0)', i) ||
            /^\(\?(?:R|\d+|&[a-zA-Z0-9_]+)\)/.test(pattern.substring(i))
          ) {
            hasRecursion = true;
            if (!targetEngine.supportsRecursion) {
              errorsFound = true;
            }
            output += '(';
            i++;
            continue;
          }

          // Atomic group (?>...)
          if (pattern.startsWith('(?>', i)) {
            hasAtomicGroup = true;
            if (!targetEngine.supportsAtomicGroups) {
              output += '(?:';
              i += 3;
              continue;
            }
            output += '(?>';
            i += 3;
            continue;
          }

          // Named groups: (?P<name>...) or (?<name>...)
          if (pattern.startsWith('(?P<', i)) {
            const endAngle = pattern.indexOf('>', i + 4);
            if (endAngle !== -1) {
              const groupName = pattern.substring(i + 4, endAngle);
              if (targetEngine.id === 'javascript') {
                output += '(?<' + groupName + '>';
                hasNamedGroupTranspiled = true;
              } else {
                output += '(?P<' + groupName + '>';
              }
              i = endAngle + 1;
              continue;
            }
          } else if (pattern.startsWith('(?<', i)) {
            if (!pattern.startsWith('(?<=', i) && !pattern.startsWith('(?<!', i)) {
              const endAngle = pattern.indexOf('>', i + 3);
              if (endAngle !== -1) {
                const groupName = pattern.substring(i + 3, endAngle);
                if (
                  targetEngine.id === 'go_re2' ||
                  targetEngine.id === 'python' ||
                  targetEngine.id === 'rust'
                ) {
                  output += '(?P<' + groupName + '>';
                  hasNamedGroupTranspiled = true;
                } else {
                  output += '(?<' + groupName + '>';
                }
                i = endAngle + 1;
                continue;
              }
            }
          }
        }

        output += '(';
        i++;
        continue;
      }

      // Check group closing ) followed by possessive quantifier
      if (char === ')') {
        output += ')';
        i++;

        const pq = checkPossessiveQuantifier(pattern, i, targetEngine);
        if (pq) {
          output += pq.output;
          i = pq.newIndex;
          if (pq.converted) hasPossessiveQuantifier = true;
        }
        continue;
      }

      // Check possessive quantifier after token
      const pq = checkPossessiveQuantifier(pattern, i, targetEngine);
      if (pq && pq.converted) {
        output += pq.output;
        i = pq.newIndex;
        hasPossessiveQuantifier = true;
        continue;
      }

      output += char;
      i++;
    }

    if (hasLookaround && !targetEngine.supportsLookaround) {
      diagnostics.push(DIAGNOSTIC_CODES.ERR_UNSUPPORTED_LOOKAROUND);
    }
    if (hasBackreference && !targetEngine.supportsBackreferences) {
      diagnostics.push(DIAGNOSTIC_CODES.ERR_UNSUPPORTED_BACKREFERENCE);
    }
    if (hasRecursion && !targetEngine.supportsRecursion) {
      diagnostics.push(DIAGNOSTIC_CODES.ERR_UNSUPPORTED_RECURSION);
    }
    if (hasAtomicGroup && !targetEngine.supportsAtomicGroups) {
      diagnostics.push(DIAGNOSTIC_CODES.WARN_ATOMIC_GROUP_CONVERTED);
    }
    if (hasPossessiveQuantifier && !targetEngine.supportsPossessiveQuantifiers) {
      diagnostics.push(DIAGNOSTIC_CODES.WARN_POSSESSIVE_QUANTIFIER_CONVERTED);
    }
    if (hasNamedGroupTranspiled) {
      diagnostics.push(DIAGNOSTIC_CODES.WARN_NAMED_GROUP_SYNTAX_TRANSPILLED);
    }

    const isCompatible = !errorsFound;

    if (isCompatible && targetEngine.isLinearTime) {
      diagnostics.push(DIAGNOSTIC_CODES.INFO_LINEAR_TIME_GUARANTEED);
    }

    return {
      engine: targetEngine.id,
      engineName: targetEngine.name,
      ecosystem: targetEngine.ecosystem,
      isCompatible,
      isLinearTime: targetEngine.isLinearTime,
      transpiledPattern: output,
      diagnostics,
    };
  }

  function checkPossessiveQuantifier(pattern, index, targetEngine) {
    const length = pattern.length;
    if (index >= length) return null;

    const char = pattern[index];

    if ((char === '+' || char === '*' || char === '?') && index + 1 < length && pattern[index + 1] === '+') {
      if (!targetEngine.supportsPossessiveQuantifiers) {
        return { output: char, newIndex: index + 2, converted: true };
      }
      return { output: char + '+', newIndex: index + 2, converted: false };
    }

    if (char === '{') {
      const closeBrace = pattern.indexOf('}', index + 1);
      if (closeBrace !== -1 && closeBrace + 1 < length && pattern[closeBrace + 1] === '+') {
        const quantifierBody = pattern.substring(index, closeBrace + 1);
        if (!targetEngine.supportsPossessiveQuantifiers) {
          return { output: quantifierBody, newIndex: closeBrace + 2, converted: true };
        }
        return { output: quantifierBody + '+', newIndex: closeBrace + 2, converted: false };
      }
    }

    return null;
  }

  /**
   * UI Initialization and Event Handling
   */
  function init() {
    const appEl = document.getElementById('regex-transpiler-app');
    if (!appEl) return;

    const sourcePatternInput = document.getElementById('source-pattern-input');
    const testTextInput = document.getElementById('test-text-input');
    const targetOutputEl = document.getElementById('target-output-code');
    const targetStatsEl = document.getElementById('target-stats');
    const inputStatsEl = document.getElementById('input-stats');
    const matchOutputEl = document.getElementById('match-results-preview');
    const matchStatusEl = document.getElementById('match-status-badge');
    const advisoriesContainer = document.getElementById('advisories-container');
    const matrixGridEl = document.getElementById('compatibility-matrix-grid');

    const btnClear = document.getElementById('btn-clear-input');
    const btnCopyTarget = document.getElementById('btn-copy-pattern');
    const btnCopySnippet = document.getElementById('btn-copy-snippet');

    let currentSourceEngine = 'pcre';
    let currentTargetEngine = 'go_re2';

    function renderUI() {
      const sourcePattern = sourcePatternInput ? sourcePatternInput.value : '';
      const testText = testTextInput ? testTextInput.value : '';

      // Update source stats
      if (inputStatsEl) {
        inputStatsEl.textContent = `${sourcePattern.length} chars`;
      }

      // Transpile
      const result = transpileRegex(sourcePattern, currentSourceEngine, currentTargetEngine);

      // Output transpiled regex
      if (targetOutputEl) {
        targetOutputEl.textContent = result.transpiledPattern || '(Empty pattern)';
      }
      if (targetStatsEl) {
        if (!result.isCompatible) {
          targetStatsEl.innerHTML = '<span class="status-pill status-error">⛔ Incompatible</span>';
        } else if (result.warnings.length > 0) {
          targetStatsEl.innerHTML = '<span class="status-pill status-warning">⚠ Transpiled with Warnings</span>';
        } else {
          targetStatsEl.innerHTML = '<span class="status-pill status-success">✓ 100% Compatible</span>';
        }
      }

      // Render Diagnostics
      renderDiagnostics(result, advisoriesContainer);

      // Render Compatibility Matrix
      renderMatrix(result.matrix, matrixGridEl, currentTargetEngine);

      // Run live match evaluation against test text
      renderLiveMatches(result, testText, matchOutputEl, matchStatusEl);
    }

    function renderDiagnostics(result, container) {
      if (!container) return;

      if (result.diagnostics.length === 0) {
        container.innerHTML = `
          <div class="advisory-card advisory-empty">
            <p>No syntax warnings or incompatibility issues found.</p>
          </div>
        `;
        return;
      }

      let html = '';
      for (const diag of result.diagnostics) {
        const severityClass = `advisory-${diag.severity}`;
        const icon = diag.severity === 'error' ? '⛔' : diag.severity === 'warning' ? '⚠' : 'ℹ';

        html += `
          <div class="advisory-card ${severityClass}">
            <div class="advisory-icon">${icon}</div>
            <div class="advisory-body">
              <div class="advisory-header">
                <strong>${escapeHtml(diag.title)}</strong>
                <span class="advisory-code">${escapeHtml(diag.code || '')}</span>
              </div>
              <p>${escapeHtml(diag.description)}</p>
            </div>
          </div>
        `;
      }
      container.innerHTML = html;
    }

    function renderMatrix(matrix, container, selectedTarget) {
      if (!container) return;

      let html = '';
      for (const item of matrix) {
        const isSelected = item.engine === selectedTarget;
        const cardClass = isSelected ? 'matrix-card active-target' : 'matrix-card';
        const linearBadge = item.isLinearTime
          ? '<span class="matrix-badge badge-linear" title="Guaranteed O(N) DFA execution">O(N) ReDoS Safe</span>'
          : '<span class="matrix-badge badge-backtrack" title="Backtracking engine">Backtracking</span>';

        const statusBadge = !item.isCompatible
          ? '<span class="matrix-badge badge-incompatible">Incompatible</span>'
          : item.diagnostics.some((d) => d.severity === 'warning')
          ? '<span class="matrix-badge badge-warning">Adapted</span>'
          : '<span class="matrix-badge badge-compatible">Compatible</span>';

        html += `
          <div class="${cardClass}" data-engine="${item.engine}">
            <div class="matrix-card-header">
              <div class="engine-title-wrap">
                <strong>${escapeHtml(item.engineName)}</strong>
                <span class="engine-eco">${escapeHtml(item.ecosystem)}</span>
              </div>
              <div class="matrix-badges">
                ${linearBadge}
                ${statusBadge}
              </div>
            </div>
            <div class="matrix-code-box">
              <code>${escapeHtml(item.transpiledPattern || '(Empty)')}</code>
            </div>
            ${
              item.diagnostics.length > 0
                ? `<ul class="matrix-diag-list">
                    ${item.diagnostics
                      .map(
                        (d) =>
                          `<li class="diag-${d.severity}"><strong>${d.severity.toUpperCase()}:</strong> ${escapeHtml(
                            d.title
                          )}</li>`
                      )
                      .join('')}
                  </ul>`
                : ''
            }
          </div>
        `;
      }
      container.innerHTML = html;
    }

    function renderLiveMatches(result, testText, outputEl, statusEl) {
      if (!outputEl) return;

      if (!testText) {
        outputEl.innerHTML = '<span class="text-muted">Enter test string to view live regex matches...</span>';
        if (statusEl) statusEl.textContent = 'No input string';
        return;
      }

      if (!result.sourcePattern) {
        outputEl.textContent = testText;
        if (statusEl) statusEl.textContent = 'No pattern';
        return;
      }

      try {
        // Convert (?P<name>...) to (?<name>...) for JS RegExp runner
        const jsPattern = result.sourcePattern.replace(/\(\?P<([a-zA-Z0-9_]+)>/g, '(?<$1>');
        const rx = new RegExp(jsPattern, 'g');

        let match;
        let lastIndex = 0;
        let highlightedHtml = '';
        let matchCount = 0;

        while ((match = rx.exec(testText)) !== null) {
          matchCount++;
          const matchStart = match.index;
          const matchEnd = matchStart + match[0].length;

          // Non-matched segment
          highlightedHtml += escapeHtml(testText.substring(lastIndex, matchStart));

          // Matched segment
          highlightedHtml += `<mark class="regex-match" title="Match #${matchCount}">${escapeHtml(
            match[0]
          )}</mark>`;

          lastIndex = matchEnd;

          // Avoid infinite loops on zero-length matches
          if (match[0].length === 0) {
            rx.lastIndex++;
          }
        }

        highlightedHtml += escapeHtml(testText.substring(lastIndex));
        outputEl.innerHTML = highlightedHtml || escapeHtml(testText);

        if (statusEl) {
          statusEl.innerHTML =
            matchCount > 0
              ? `<span class="badge-success">✓ ${matchCount} match${matchCount > 1 ? 'es' : ''} found</span>`
              : '<span class="badge-neutral">0 matches</span>';
        }
      } catch (err) {
        outputEl.textContent = testText;
        if (statusEl) {
          statusEl.innerHTML = `<span class="badge-error">Regex evaluation notice: ${escapeHtml(
            err.message
          )}</span>`;
        }
      }
    }

    // Engine toggle listeners
    document.querySelectorAll('[data-source-engine]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-source-engine]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        currentSourceEngine = btn.getAttribute('data-source-engine');
        renderUI();
      });
    });

    document.querySelectorAll('[data-target-engine]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-target-engine]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        currentTargetEngine = btn.getAttribute('data-target-engine');
        renderUI();
      });
    });

    // Preset buttons
    document.querySelectorAll('[data-preset]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const presetKey = btn.getAttribute('data-preset');
        const preset = PRESETS[presetKey];
        if (preset) {
          if (sourcePatternInput) sourcePatternInput.value = preset.pattern;
          if (testTextInput && preset.testText) testTextInput.value = preset.testText;

          // Activate source engine
          currentSourceEngine = preset.sourceEngine;
          document.querySelectorAll('[data-source-engine]').forEach((b) => {
            b.classList.toggle('active', b.getAttribute('data-source-engine') === currentSourceEngine);
          });

          // Activate target engine
          currentTargetEngine = preset.targetEngine;
          document.querySelectorAll('[data-target-engine]').forEach((b) => {
            b.classList.toggle('active', b.getAttribute('data-target-engine') === currentTargetEngine);
          });

          renderUI();
        }
      });
    });

    // Input listeners
    if (sourcePatternInput) {
      sourcePatternInput.addEventListener('input', renderUI);
    }
    if (testTextInput) {
      testTextInput.addEventListener('input', renderUI);
    }

    // Clear input
    if (btnClear) {
      btnClear.addEventListener('click', () => {
        if (sourcePatternInput) sourcePatternInput.value = '';
        if (testTextInput) testTextInput.value = '';
        renderUI();
      });
    }

    // Copy pattern
    if (btnCopyTarget) {
      btnCopyTarget.addEventListener('click', async () => {
        const text = targetOutputEl ? targetOutputEl.textContent : '';
        if (!text) return;
        try {
          await navigator.clipboard.writeText(text);
          const original = btnCopyTarget.innerHTML;
          btnCopyTarget.innerHTML = '✓ Copied!';
          setTimeout(() => {
            btnCopyTarget.innerHTML = original;
          }, 2000);
        } catch (_) {
          // fallback
        }
      });
    }

    // Copy code snippet
    if (btnCopySnippet) {
      btnCopySnippet.addEventListener('click', async () => {
        const text = targetOutputEl ? targetOutputEl.textContent : '';
        let snippet = '';
        if (currentTargetEngine === 'go_re2') {
          snippet = `var rx = regexp.MustCompile(\`${text}\`)`;
        } else if (currentTargetEngine === 'python') {
          snippet = `rx = re.compile(r'${text}')`;
        } else if (currentTargetEngine === 'rust') {
          snippet = `let rx = Regex::new(r"${text}").unwrap();`;
        } else {
          snippet = `const rx = /${text}/g;`;
        }

        try {
          await navigator.clipboard.writeText(snippet);
          const original = btnCopySnippet.innerHTML;
          btnCopySnippet.innerHTML = '✓ Snippet Copied!';
          setTimeout(() => {
            btnCopySnippet.innerHTML = original;
          }, 2000);
        } catch (_) {
          // fallback
        }
      });
    }

    // Initial default preset load
    if (PRESETS.password_lookahead && sourcePatternInput && !sourcePatternInput.value) {
      sourcePatternInput.value = PRESETS.password_lookahead.pattern;
      if (testTextInput) testTextInput.value = PRESETS.password_lookahead.testText;
    }

    renderUI();
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  }

  // Export for testing
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
      transpileRegex,
      evaluateEngine,
      ENGINES,
      DIAGNOSTIC_CODES,
      PRESETS,
    };
  }
})();
