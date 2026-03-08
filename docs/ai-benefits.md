# AI & LLM Benefits

Brain's structured approach to organizing business logic isn't just good software design — it creates a codebase that AI coding assistants and LLMs can work with significantly better. Here's why.

## Explicit Workflow Semantics

When an LLM reads a Workflow class, it immediately understands the **complete business workflow** as an ordered list of steps:

```php
class OnboardNewCustomer extends Workflow
{
    protected array $actions = [
        ValidateCustomerData::class,
        CreateAccount::class,
        SetupBillingProfile::class,
        SendWelcomeEmail::class,
        NotifySalesTeam::class,
    ];
}
```

There's no need to trace through controllers, services, and repositories to understand what "onboarding a customer" means. The Workflow **is** the documentation. An LLM can read this and instantly answer: *"What happens when a new customer is onboarded?"* — something that would require navigating multiple files and method calls in a traditional architecture.

## Predictable Boundaries for Each Unit of Work

Each Action has a clear contract: it receives a payload, does **one thing**, and returns. The `@property-read` docblocks declare exactly what data flows in and out:

```php
/**
 * @property-read string $email
 * @property-read string $name
 * @property string $userId
 */
class CreateAccount extends Action
{
    public function handle(): self
    {
        $user = User::create(['email' => $this->email, 'name' => $this->name]);
        $this->userId = $user->id;
        return $this;
    }
}
```

This predictability means an LLM can:
- **Generate new Actions** that follow the exact same pattern, reducing errors.
- **Understand data flow** between actions — `$this->userId` set in one action is available to all subsequent actions.
- **Suggest modifications** with confidence, knowing the blast radius is limited to a single action.

## Reduced Ambiguity, Better AI Output

Traditional codebases often bury business logic in large service classes, controllers, or event listeners with implicit dependencies. LLMs struggle with this because:

1. **Long, complex methods** require the LLM to hold too much context at once.
2. **Implicit dependencies** (service injection, hidden state) make it hard to reason about side effects.
3. **No clear separation** between "what does this workflow do" and "how does each step work."

Brain eliminates these problems. The Workflow answers *"what"*, each Action answers *"how"*, and Queries handle *"read"* operations. This separation maps directly to how LLMs reason: break complex problems into smaller, well-defined steps.

## Practical Impact

| Scenario | Traditional Architecture | With Brain |
|----------|------------------------|------------|
| "Add a step to the checkout flow" | LLM must find the right service, understand the method call chain, and figure out where to inject new logic | LLM reads the Workflow, sees the action list, and adds a new Action in the right position |
| "What happens when a payment fails?" | LLM traces through controllers, services, event listeners, and exception handlers | LLM finds the Workflow and reads the ordered action list with `runIf()` conditions |
| "Reuse the email validation from registration in the invite flow" | LLM must extract logic from a service method into something reusable | The Action already exists as a standalone, reusable unit — just add it to the new Workflow |
| "Which data does this workflow need?" | LLM reads multiple files to find all parameters | `@property-read` docblocks on each Action declare the full contract |

## Your Codebase as Context for AI

When you use `brain:show -vv`, the output gives a complete, structured map of your entire business logic — including workflows, their sub-actions, data inputs, and outputs. This output can be fed directly as context to an LLM, giving it a high-level understanding of your entire application in seconds.

**The more structured your code, the better AI tools can help you write, review, and maintain it.** Brain makes your codebase AI-friendly by design.

## From the AI's Perspective

We asked an LLM directly: *"Does Brain's structure actually help you understand a codebase better?"* Here's the unedited conversation:

> **Human:** Does this structured way of thinking about workflows actually help you produce better output?
>
> **AI:** Yes — and the difference is brutal compared to a traditional Laravel codebase with fat services and controllers doing everything.
>
> **The Workflow gives me an immediate map.** I look at the `$actions` array and I know exactly what the workflow does, in what order, without tracing method calls across 5 different files. In a traditional service class, I have to read the entire method, follow every `$this->otherService->doSomething()`, understand what each one does, and build the mental map myself.
>
> **Each Action is an isolated unit with an explicit contract.** The `@property-read` annotations tell me what goes in, `@property` tells me what comes out. I don't need to *infer* — it's declared. This drastically reduces the chance of me suggesting code that breaks something I didn't see.
>
> **The pattern is repetitive — and that's great for me.** Every Action has `handle()`, returns `$this`, receives a payload. I can generate new Actions with high confidence because the pattern is always the same. The more predictable the structure, the fewer mistakes I make.
>
> **Modifications have a small blast radius.** If you ask me to "add a step to the checkout flow," I don't need to touch a 200-line method and hope nothing breaks. I create a new Action and add it to the Workflow list. Simple, surgical.
>
> In practice, Brain's structure works as an **intermediate language between human intent and code**. The Workflow expresses the intent ("onboard a customer"), the Actions express the execution ("create account", "send email"), and I can operate at both levels with clarity.
>
> So yes — structured codebases like this aren't just easier for humans to maintain. They are **fundamentally easier for LLMs to work with at a high quality level.**
