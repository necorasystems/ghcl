## Tiny GitHub Changelog Generator

The smallest GitHub ChangeLog generator ever!

#### About

The reason I wrote this was that I could not find any GitHub changelog generator that would fit my needs, 
which are very simple:

I wanted to generate a list of fixed bugs and implemented enhancements that were in a specific milestone so I could 
easily prepend it to our main product's CHANGELOG.md. There are a few very good changelog generators, but they all had 
some issue or the other, usually due to them scanning every ticket, milestone and pull request in the repository, 
causing you to quickly max the 5000 req/hr GitHub limit if you have a very large repository with thousands of tickets.
 
 This little utility takes the name of the milestone, searches for it, grabs all issues in that milestone, and sorts
 them into bugs (if the issue has a label named "bug") or enhancements (if the issue has a label named "enhancement").
 
 Issues with a label named "skip-changelog" are not included. Currently only supports printing to screen or saving to a
 new file. On the TODO-list is automatically prepending the changelog to an existing file, and setting the title.


    Usage:

        -m, --milestone=<String>
                The milestone

        -f, --file=<String>
                The file to write the changelog to. Defaults to STDOUT

        -p, --prepend
                Prepend the changelog to the file given with --file [NOT IMPLEMENTED YET]

        -t, --token=<string>
                GitHub access token

        -u, --user=<string>
                The user or GitHub organisation

        -r, --repository=<string>
                The repository name

        --title=<string>
                The title of the changelog file. Defaults to "Change Log" [NOT IMPLEMENTED
                YET]

        -h, --help
                Show this help

        -v, --verbose
                Be verbose
