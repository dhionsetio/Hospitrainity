import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { evaluatePasswordStrength } from '../../resources/js/password-strength.js';

describe('evaluatePasswordStrength', () => {
    it('returns weak for empty or short password below minLength', () => {
        const result = evaluatePasswordStrength('Short1!', 15);
        assert.equal(result.level, 'weak');
        assert.equal(result.percent, 25);
    });

    it('returns weak for common weak passwords', () => {
        const result = evaluatePasswordStrength('password123', 8);
        assert.equal(result.level, 'weak');
    });

    it('returns good or strong for passwords with length and variety', () => {
        const goodResult = evaluatePasswordStrength('Pass1234Word', 8);
        assert.ok(['good', 'strong'].includes(goodResult.level));
    });

    it('returns very_strong for long passwords with upper/lower/numbers/symbols', () => {
        const result = evaluatePasswordStrength('Correct-Horse-Battery-Staple-2026!#', 15);
        assert.equal(result.level, 'very_strong');
        assert.equal(result.percent, 100);
    });
});
