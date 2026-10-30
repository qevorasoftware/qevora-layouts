#!/usr/bin/env python3
"""Generate languages/qevora-layouts.pot from the plugin sources.

A dependency-free replacement for `wp i18n make-pot`, which needs Composer
packages that are not available in every build environment. It understands the
gettext calls WordPress plugins actually use:

    __( 'text', 'qevora-layouts' )          _e( ... )
    esc_html__( ... )  esc_attr__( ... )    esc_html_e( ... )  esc_attr_e( ... )
    _x( 'text', 'context', domain )         _ex( ... )
    _n( 'single', 'plural', $n, domain )    _nx( ... )

Translator comments (`/* translators: ... */` or `// translators: ...`) placed
immediately before a call are attached to the entry, exactly like the WordPress
generator does.

Usage:
    python3 tools/make-pot.py [plugin-root]

@package QevoraLayouts
"""

import datetime
import os
import re
import sys

DOMAIN = 'qevora-layouts'
PROJECT = 'Qevora Layouts'
VERSION = '1.0.0'
BUGS = 'https://github.com/qevorasoftware/qevora-layouts/issues'

# Function name => index of the context argument, None when it has no context.
SINGLE = {
    '__': None,
    '_e': None,
    'esc_html__': None,
    'esc_html_e': None,
    'esc_attr__': None,
    'esc_attr_e': None,
    '_x': 1,
    '_ex': 1,
}

PLURAL = {
    '_n': (0, 1),
    '_nx': (0, 1),
}

WRAP = 76


def read(path):
    with open(path, 'r', encoding='utf-8') as handle:
        return handle.read()


def strip_comments_keep_translators(source):
    """Returns a copy of the source with comments blanked out.

    Comments are replaced by spaces so every character offset - and therefore
    every line number - stays intact. Translator comments are collected
    separately, keyed by the line they end on.
    """
    out = list(source)
    translators = {}
    length = len(source)
    index = 0
    in_single = False
    in_double = False

    while index < length:
        char = source[index]
        following = source[index:index + 2]

        if in_single:
            if char == '\\':
                index += 2
                continue
            if char == "'":
                in_single = False
            index += 1
            continue

        if in_double:
            if char == '\\':
                index += 2
                continue
            if char == '"':
                in_double = False
            index += 1
            continue

        if char == "'" :
            in_single = True
            index += 1
            continue

        if char == '"':
            in_double = True
            index += 1
            continue

        if following == '//':
            end = source.find('\n', index)
            end = length if end == -1 else end
            text = source[index:end]
            match = re.match(r'//\s*translators:\s*(.*)$', text, re.IGNORECASE)
            if match:
                translators[end] = match.group(1).strip()
            for position in range(index, end):
                if source[position] != '\n':
                    out[position] = ' '
            index = end
            continue

        if following == '/*':
            end = source.find('*/', index + 2)
            end = length if end == -1 else end + 2
            text = source[index:end]
            match = re.match(r'/\*+\s*translators:\s*(.*?)\s*\*+/$', text, re.IGNORECASE | re.DOTALL)
            if match:
                note = re.sub(r'\s*\n\s*\*?\s*', ' ', match.group(1)).strip()
                translators[end] = note
            for position in range(index, end):
                if source[position] != '\n':
                    out[position] = ' '
            index = end
            continue

        if following == '#[' or char == '#':
            # Attributes and shell-style comments: only outside strings.
            if char == '#' and following != '#[':
                end = source.find('\n', index)
                end = length if end == -1 else end
                for position in range(index, end):
                    if source[position] != '\n':
                        out[position] = ' '
                index = end
                continue

        index += 1

    return ''.join(out), translators


def split_args(raw):
    """Splits a call argument list on top-level commas."""
    args = []
    depth = 0
    current = ''
    quote = None
    index = 0

    while index < len(raw):
        char = raw[index]

        if quote:
            current += char
            if char == '\\' and index + 1 < len(raw):
                current += raw[index + 1]
                index += 2
                continue
            if char == quote:
                quote = None
            index += 1
            continue

        if char in ("'", '"'):
            quote = char
            current += char
            index += 1
            continue

        if char in '([{':
            depth += 1
        elif char in ')]}':
            depth -= 1

        if char == ',' and depth == 0:
            args.append(current.strip())
            current = ''
            index += 1
            continue

        current += char
        index += 1

    if current.strip():
        args.append(current.strip())

    return args


def literal(value):
    """Returns the string a PHP literal holds, or None when it is dynamic."""
    value = value.strip()

    if len(value) >= 2 and value[0] == "'" and value[-1] == "'":
        body = value[1:-1]
        return body.replace("\\'", "'").replace('\\\\', '\\')

    if len(value) >= 2 and value[0] == '"' and value[-1] == '"':
        body = value[1:-1]
        body = re.sub(r'\\([nrtfve"\\$])', lambda m: {
            'n': '\n', 'r': '\r', 't': '\t', 'f': '\f', 'v': '\v',
            'e': '\x1b', '"': '"', '\\': '\\', '$': '$',
        }[m.group(1)], body)
        return body

    return None


def find_calls(source):
    """Yields (name, args, end_offset) for every gettext call in the source."""
    names = sorted(list(SINGLE.keys()) + list(PLURAL.keys()), key=len, reverse=True)
    pattern = re.compile(r'(?<![\w>$:])(' + '|'.join(re.escape(name) for name in names) + r')\s*\(')

    for match in pattern.finditer(source):
        name = match.group(1)
        start = match.end()
        depth = 1
        index = start
        quote = None

        while index < len(source) and depth > 0:
            char = source[index]

            if quote:
                if char == '\\':
                    index += 2
                    continue
                if char == quote:
                    quote = None
                index += 1
                continue

            if char in ("'", '"'):
                quote = char
            elif char == '(':
                depth += 1
            elif char == ')':
                depth -= 1
                if depth == 0:
                    break

            index += 1

        if depth != 0:
            continue

        yield name, source[start:index], index + 1


def escape(text):
    text = text.replace('\\', '\\\\').replace('"', '\\"')
    text = text.replace('\t', '\\t').replace('\r', '\\r').replace('\n', '\\n')
    return text


def quoted(text):
    """Renders a gettext string, wrapped like the reference tools do."""
    escaped = escape(text)

    if len(escaped) + 9 <= WRAP and '\n' not in text:
        return ['"%s"' % escaped]

    lines = []
    current = ''

    for word in escaped.split(' '):
        candidate = (current + ' ' + word).strip()
        if len(candidate) + 2 > WRAP and current:
            lines.append('"%s"' % current)
            current = word
        else:
            current = candidate

    if current:
        lines.append('"%s"' % current)

    if len(lines) > 1:
        return ['""'] + lines

    return lines


def collect(root):
    """Returns a list of entry dictionaries found below `root`."""
    entries = {}
    order = []

    for directory, folders, files in os.walk(root):
        folders[:] = sorted(
            folder for folder in folders
            if folder not in ('.git', 'node_modules', 'vendor', 'release', 'tests')
        )

        for name in sorted(files):
            if not name.endswith('.php'):
                continue

            path = os.path.join(directory, name)
            relative = os.path.relpath(path, root).replace(os.sep, '/')
            source = read(path)
            cleaned, translators = strip_comments_keep_translators(source)

            for call, raw_args, end in find_calls(cleaned):
                args = split_args(raw_args)
                domain_index = None

                for position, argument in enumerate(args):
                    if literal(argument) == DOMAIN:
                        domain_index = position
                        break

                if domain_index is None:
                    continue

                if call in PLURAL:
                    singular_index, plural_index = PLURAL[call]
                    # _nx( single, plural, number, context, domain ).
                    context_index = 3 if call == '_nx' else None
                else:
                    singular_index = 0
                    plural_index = None
                    context_index = SINGLE[call]

                if len(args) <= singular_index:
                    continue

                singular = literal(args[singular_index])

                if singular is None:
                    continue

                plural = None
                if plural_index is not None and len(args) > plural_index:
                    plural = literal(args[plural_index])

                context = None
                if context_index is not None and len(args) > context_index:
                    context = literal(args[context_index])

                line = cleaned.count('\n', 0, end) + 1
                note = translators.get(end)

                if note is None:
                    # The comment may sit on the line before the call started.
                    start_line = cleaned.count('\n', 0, end - len(raw_args))
                    for offset, text in translators.items():
                        if cleaned.count('\n', 0, offset) in (start_line - 1, start_line - 2):
                            note = text
                            break

                key = (context or '', singular, plural or '')

                if key not in entries:
                    entries[key] = {
                        'context': context,
                        'singular': singular,
                        'plural': plural,
                        'references': [],
                        'notes': [],
                    }
                    order.append(key)

                reference = '%s:%d' % (relative, line)

                if reference not in entries[key]['references']:
                    entries[key]['references'].append(reference)

                if note and note not in entries[key]['notes']:
                    entries[key]['notes'].append(note)

    return [entries[key] for key in order]


def render(entries):
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S+00:00')
    year = datetime.datetime.now(datetime.timezone.utc).year

    out = [
        '# Copyright (C) %d Qevora Software' % year,
        '# This file is distributed under the GPL-2.0-or-later license.',
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: %s %s\\n"' % (PROJECT, VERSION),
        '"Report-Msgid-Bugs-To: %s\\n"' % BUGS,
        '"POT-Creation-Date: %s\\n"' % now,
        '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"',
        '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"',
        '"Language-Team: LANGUAGE <LL@li.org>\\n"',
        '"Language: \\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
        '"X-Domain: %s\\n"' % DOMAIN,
        '',
    ]

    for entry in entries:
        for note in entry['notes']:
            out.append('#. translators: %s' % note)

        for reference in entry['references']:
            out.append('#: %s' % reference)

        if entry['context']:
            out.append('msgctxt ' + quoted(entry['context'])[0])
            out.extend(quoted(entry['context'])[1:])

        singular = quoted(entry['singular'])
        out.append('msgid ' + singular[0])
        out.extend(singular[1:])

        if entry['plural']:
            plural = quoted(entry['plural'])
            out.append('msgid_plural ' + plural[0])
            out.extend(plural[1:])
            out.append('msgstr[0] ""')
            out.append('msgstr[1] ""')
        else:
            out.append('msgstr ""')

        out.append('')

    return '\n'.join(out)


def main():
    root = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    root = os.path.abspath(root)

    entries = collect(root)
    entries.sort(key=lambda entry: (entry['references'][0], entry['singular']))

    target = os.path.join(root, 'languages', '%s.pot' % DOMAIN)
    os.makedirs(os.path.dirname(target), exist_ok=True)

    with open(target, 'w', encoding='utf-8') as handle:
        handle.write(render(entries))

    print('Wrote %s (%d strings)' % (os.path.relpath(target, root), len(entries)))


if __name__ == '__main__':
    main()
