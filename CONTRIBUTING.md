# Contributing Guidelines

### EditorConfig

In order to maintain consistent code styling, please follow the formats specified in [.editorconfig](.editorconfig). Information on how to use this file can be found at [editorconfig.org](https://editorconfig.org/).

### Additional Formatting Guidelines

_If any of the below can be codified into the .editorconfig, please do so!_

##### Shell Scripts

Shell scripts should be written in bash with the `#!/bin/bash` shebang.

Shell scripts should pass [ShellCheck](https://www.shellcheck.net/); if there are any recommendations please follow them.

Variables:

- Variable names should be in `$SCREAMING_SNAKE_CASE`
- Braceless `$VARIABLE` syntax is generally preferable to `${VARIABLE}` unless the braces are needed for mid-string parsing or using parameter expansion

Strings:

- Quoting is generally preferable to escaping
- The entire string should generally be quoted, not just the variable
- Double quotes are generally preferable to single quotes, unless avoiding variable substitution (e.g., in awk programs) or dealing with strings within strings (e.g., SQL queries)
- Here documents are generally preferable to quoted multiline strings

Tests:

- Double brackets are generally preferable to single brackets or `test`
- Single ` = ` is generally preferable to double ` == `
- To test if a string is not empty, `[[ string ]]` is generally preferable to `[[ -n string ]]` or `[[ string != "" ]]`
- To test if a string is empty, `[[ -z string ]]` is generally preferable to `[[ string = "" ]]`

Functions:

- Function names should be in `camelCase`
- The `funcName() {}` syntax is generally preferable to `function funcName {}`

Parsing:

- Multiple grep, sed, awk, head, tail, cut, tr, etc. statements should be combined into a single awk program
