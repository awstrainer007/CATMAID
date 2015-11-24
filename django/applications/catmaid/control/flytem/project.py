import json
from django.http import HttpResponse
from catmaid.control.flytem.models import FlyTEMProjectStacks


def projects(request):
    """ Returns a list of project objects that are visible for the requesting
    user and that have at least one stack linked to it.
    """
    project_stacks = FlyTEMProjectStacks()

    projects = []
    for p in project_stacks.data:
        projects.append({
            'id': p['stack'],
            'title': '%s: %s' % (p['project'], p['stack']),
            'comment': '',
            'stacks': [
                {
                    'id': p['stack'],
                    'title': p['stack'],
                    'comment': '<p>Owner: %s</p>' % p['owner'],
                }
            ],
            'stackgroups': []
        })

    return HttpResponse(json.dumps(projects, sort_keys=True, indent=2),
                        content_type="text/json")
