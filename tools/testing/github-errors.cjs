/** Expose bounded test failures as native GitHub Actions annotations without a logging dependency. */
'use strict';

/**
 * Keep the actual failure readable when public Actions job logs are unavailable.
 *
 * Emit only on the hosted runner and prefer the original stack over its message.
 * Limit input to 8192 UTF-16 code units before escaping, so the single protocol
 * line remains below 25 KiB even for percent/newline-heavy or Unicode failures.
 * GitHub's own toolkit escapes percent before CR/LF; no command properties are
 * interpolated. Callers retain their normal console output and failing exit code.
 *
 * @param {*} error Caught test failure, normally an Error with its original stack.
 * @return {void}
 * @since 3.0.0
 * @see https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-an-error-message
 * @see https://github.com/actions/toolkit/blob/main/packages/core/src/command.ts
 */
function annotateGitHubError(error) {
	if (process.env.GITHUB_ACTIONS !== 'true') {
		return;
	}
	const source = error && typeof error.stack === 'string' && error.stack ? error.stack :
		error && typeof error.message === 'string' && error.message ? error.message : String(error);
	const limit = 8192;
	const message = source.length > limit ? source.slice(0, limit) + '\n[Diagnostic truncated; the complete error remains in the job log.]' : source;
	const escaped = message.replace(/%/g, '%25').replace(/\r/g, '%0D').replace(/\n/g, '%0A');
	process.stdout.write('::error::' + escaped + '\n');
}

module.exports = {annotateGitHubError};
