document.addEventListener('DOMContentLoaded', () => {
  console.log('SWS: search.js loaded')
  console.log(window.ss.config)
  const targetElement = document.getElementById(
    'Menu-Sunnysideup-SiteWideSearch-Admin-SearchAdmin'
  )

  if (targetElement) {
    const securityID =
      window.ss && window.ss.config && window.ss.config['SecurityID']
    if (securityID) {
      const holder = document.createElement('a')
      const form = document.createElement('form')
      form.setAttribute('method', 'post') // or 'post', depending on your needs
      form.setAttribute('action', '/admin/find/EditForm') // Update with your form action

      const input = document.createElement('input')
      input.setAttribute('type', 'text')
      input.setAttribute('name', 'Keywords')
      input.setAttribute('placeholder', 'Search...')

      const hiddenInput = document.createElement('input')
      hiddenInput.setAttribute('type', 'hidden')
      hiddenInput.setAttribute('name', 'SecurityID')
      hiddenInput.setAttribute('value', securityID)
      hiddenInput.setAttribute('class', 'hidden')
      // hiddenInput.setAttribute('id', 'Form_EditForm_SecurityID')

      form.appendChild(input)
      form.appendChild(hiddenInput)
      form.classList.add('sunny-side-up-quick-search-form')
      holder.appendChild(form)
      targetElement.innerHTML = holder.outerHTML
    }
  }
})
