Books for Binding
=================

This is a system of producing LaTeX-formatted books, ready for binding, from Wikisource works.

*It's a work-in-progress and not really ready for anyone to look at yet.*

## Installation

```bash
git clone https://github.com/samwilson/books-for-binding.git
cd books-for-binding
composer install --no-dev
./bin/booksforbinding --help
```

## Usage

1. Select a work on a Wikisource.
2. Download the HTML of the book:
   ```bash
   ./bin/booksforbinding download -l en -t The_Nether_World -o directory/to/save-in
   ```
   This saves the HTML to a directory e.g. `directory/to/save-in/The_Nether_World/html`.
3. Convert the HTML to LaTeX:
   ```bash
   ./bin/booksforbinding download -i directory/to/save-in/html
   ```
   This saves the LaTeX to a directory e.g. `directory/to/save-in/The_Nether_World/latex`.
4. Go into that directory and create a new Git repository with the contents therein:
   ```bash
   cd directory/to/save-in/The_Nether_World
   git init
   git add html latex
   git branch -m unchanged
   git commit -am"First commit."
   ```
   The idea is to have two branches: one for the unchanged source as it's generated by pandoc,
   and the other (the master) as the place to make edits.
   The former is merged into the latter whenever required.
5. Start editing the LaTeX files:
   ```bash
   git checkout -b master
   ```

## License

This software is copyright 2018 Sam Wilson and licensed under the GPL, version 2 or later.
