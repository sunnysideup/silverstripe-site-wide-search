document.addEventListener('DOMContentLoaded', () => {
  const targetElement = document.getElementById(
    'Menu-Sunnysideup-SiteWideSearch-Admin-SearchAdmin'
  )

  if (targetElement) {
    const form = document.createElement('form')
    form.setAttribute('method', 'post') // or 'post', depending on your needs
    form.setAttribute('action', '/admin/find/EditForm') // Update with your form action

    const input = document.createElement('input')
    input.setAttribute('type', 'text')
    input.setAttribute('name', 'Keywords')
    input.setAttribute('placeholder', 'Search...')

    form.appendChild(input)

    targetElement.replaceWith(form)
  }
})
